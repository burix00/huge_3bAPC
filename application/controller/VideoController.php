<?php

class VideoController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        Auth::checkAuthentication();
        $this->View->render('video/index', array(
            'my_files' => VideoModel::getMyVideos()
        ));
    }

    public function publicVideos()
    {
        $searchQuery = trim($_GET['search'] ?? '');

        if ($searchQuery !== '') {
            $videos = VideoModel::searchPublishedVideos($searchQuery);
        } else {
            $videos = VideoModel::getPublishedVideos();
        }

        $this->View->render('video/public', array(
            'published_videos' => $videos,
            'search_query'     => $searchQuery
        ));
    }

    public function stream($videoId)
    {
        Auth::checkAuthentication();

        $video = VideoModel::getVideoById((int) $videoId);

        if (!$video) {
            Redirect::to('video');
            exit();
        }

        $userId = Session::get('user_id');
        if ($video->is_published != 1 && $video->user_id != $userId) {
            Redirect::to('video');
            exit();
        }

        $filePath = Config::get('PATH_USERVIDEOS') . $video->user_id . '/' . $video->file_name;
        if (!file_exists($filePath)) {
            Redirect::to('video');
            exit();
        }

        // Clear any open output buffers so we can stream cleanly
        while (ob_get_level()) { ob_end_clean(); }

        // Allow unlimited execution time for large file transfers
        set_time_limit(0);

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);
        $fileSize = filesize($filePath);

        $start = 0;
        $end   = $fileSize - 1;

        // Advertise range support — browsers require this to play video
        header('Accept-Ranges: bytes');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . $video->file_name . '"');
        header('Cache-Control: private, max-age=3600');

        if (isset($_SERVER['HTTP_RANGE'])) {
            // Parse "bytes=start-end" (also handles suffix form "bytes=-500")
            if (!preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header('Content-Range: bytes */' . $fileSize);
                exit();
            }

            $rStart = ($m[1] !== '') ? (int) $m[1] : null;
            $rEnd   = ($m[2] !== '') ? (int) $m[2] : null;

            if ($rStart === null) {
                // suffix range: bytes=-N  → last N bytes
                $start = $fileSize - (int) $rEnd;
                $end   = $fileSize - 1;
            } else {
                $start = $rStart;
                $end   = ($rEnd !== null) ? (int) $rEnd : $fileSize - 1;
            }

            if ($start < 0 || $end < $start || $end >= $fileSize) {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header('Content-Range: bytes */' . $fileSize);
                exit();
            }

            header('HTTP/1.1 206 Partial Content');
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
            header('Content-Length: ' . ($end - $start + 1));
        } else {
            header('Content-Length: ' . $fileSize);
        }

        // Stream the requested byte range in 64 KB chunks
        $fp = fopen($filePath, 'rb');
        if (!$fp) {
            header('HTTP/1.1 500 Internal Server Error');
            exit();
        }

        fseek($fp, $start);
        $remaining = $end - $start + 1;

        while ($remaining > 0 && !feof($fp)) {
            $chunk      = min(65536, $remaining);
            $data       = fread($fp, $chunk);
            $remaining -= strlen($data);
            echo $data;
            flush();
        }

        fclose($fp);
        exit();
    }

    public function upload()
    {
        Auth::checkAuthentication();
        $this->View->render('video/upload');
    }
    public function uploadSave()
    {
        Auth::checkAuthentication();

        // When upload exceeds PHP's post_max_size, $_POST is wiped entirely —
        // the CSRF token disappears and isTokenValid() would falsely log the user out.
        if (!empty($_SERVER['CONTENT_LENGTH']) && empty($_POST)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_VIDEO_UPLOAD_TOO_BIG'));
            Redirect::to('video/upload');
            exit();
        }

        if (!Csrf::isTokenValid()) {
            LoginModel::logout();
            Redirect::home();
            exit();
        }

        VideoModel::uploadVideo();
        Redirect::to('video');
    }

    public function deleteVideo($videoId) {

        Auth::checkAuthentication();

        if (!Csrf::isTokenValid()) {
            LoginModel::logout();
            Redirect::home();
            exit();
        }

        VideoModel::deleteVideo((int) $videoId);
        Redirect::to('video');
    }

    public function togglePublished($videoId) {
        Auth::checkAuthentication();

        if (!Csrf::isTokenValid()) {
            LoginModel::logout();
            Redirect::home();
            exit();
        }

        VideoModel::togglePublished((int) $videoId);
        Redirect::to('video');
    }

    /**
     * AJAX endpoint: receive one chunk, return JSON progress/done/error.
     * Called repeatedly by the JS chunked-upload loop.
     */
    public function uploadChunk()
    {
        Auth::checkAuthentication();

        header('Content-Type: application/json');

        // When upload exceeds PHP's post_max_size, $_POST is wiped entirely.
        if (!empty($_SERVER['CONTENT_LENGTH']) && empty($_POST)) {
            echo json_encode(['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_UPLOAD_TOO_BIG')]);
            exit();
        }

        // Validate CSRF – return JSON error instead of logging out (this is an AJAX call)
        if (!Csrf::isTokenValid()) {
            echo json_encode(['status' => 'error', 'message' => 'CSRF validation failed.']);
            exit();
        }

        $result = VideoModel::uploadChunk();
        echo json_encode($result);
        exit();
    }

    public function viewVideo($videoId)
{
    Auth::checkAuthentication();

    $videoId = (int) $videoId;
    $video   = VideoModel::getVideoById($videoId);

    if (!$video) {
        Redirect::to('video');
        exit();
    }

    $userId = Session::get('user_id');
    if ($video->is_published != 1 && $video->user_id != $userId) {
        Redirect::to('video');
        exit();
    }

    $counts = VideoModel::getRatingCounts($videoId);

    $this->View->render('video/view', array(
        'video'     => $video,
        'comments'  => CommentModel::getCommentsByVideo($videoId),
        'likes'     => $counts['likes'],
        'dislikes'  => $counts['dislikes'],
        'user_vote' => VideoModel::getUserVote($videoId, $userId)
    ));
}

    /**
     * AJAX endpoint: like (action=like) or dislike (action=dislike) a video.
     * Returns JSON with updated counts and the user's current vote.
     */
    public function rate($videoId)
    {
        Auth::checkAuthentication();
        header('Content-Type: application/json');

        if (!Csrf::isTokenValid()) {
            echo json_encode(['status' => 'error', 'message' => 'CSRF validation failed.']);
            exit();
        }

        $videoId = (int) $videoId;
        $video   = VideoModel::getVideoById($videoId);
        $userId  = Session::get('user_id');

        if (!$video || ($video->is_published != 1 && $video->user_id != $userId)) {
            echo json_encode(['status' => 'error', 'message' => 'Video not found.']);
            exit();
        }

        $action = Request::post('action');
        if ($action !== 'like' && $action !== 'dislike') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
            exit();
        }

        echo json_encode(VideoModel::rate($videoId, $action === 'like'));
        exit();
    }

    /**
     * Add a comment to a video (standard POST, redirects back to the video page).
     */
    public function addComment($videoId)
    {
        Auth::checkAuthentication();

        if (!Csrf::isTokenValid()) {
            LoginModel::logout();
            Redirect::home();
            exit();
        }

        $videoId = (int) $videoId;
        CommentModel::addComment($videoId, Request::post('comment_text'));
        Redirect::to('video/viewVideo/' . $videoId);
    }

    /**
     * Delete one of the current user's comments, then return to the video page.
     */
    public function deleteComment($commentId, $videoId)
    {
        Auth::checkAuthentication();

        if (!Csrf::isTokenValid()) {
            LoginModel::logout();
            Redirect::home();
            exit();
        }

        CommentModel::deleteComment((int) $commentId);
        Redirect::to('video/viewVideo/' . (int) $videoId);
    }
}