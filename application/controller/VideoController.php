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
        $this->View->render('video/public', array(
            'published_videos' => VideoModel::getPublishedVideos()
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

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . $video->file_name . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, max-age=3600');
        readfile($filePath);
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

    public function viewVideo($videoId)
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

    $this->View->render('video/view', array(
        'video' => $video
    ));
}
}