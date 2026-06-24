<?php

/**
 * VideoModel
 * Handles all database and filesystem operations for user videos.
 */
class VideoModel
{
    /** Allowed MIME types for upload */
    private static $allowedMimeTypes = ['video/mp4', 'video/webm', 'video/ogg'];

    /** Maximum upload size for single-request uploads: 200 MB */
    private static $maxFileSize = 209715200;

    /** Maximum upload size for chunked uploads: 10 GB */
    private static $maxChunkedFileSize = 10737418240;

    /** Allowed MIME types for thumbnails */
    private static $allowedThumbMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** Maximum thumbnail file size: 5 MB */
    private static $maxThumbSize = 5242880;

    /**
     * Save a thumbnail image to the user's video directory.
     * Validates MIME type and size. Returns the stored filename on success, null on failure (non-fatal).
     *
     * @param  string $sourcePath     Absolute path to the source image.
     * @param  int    $userId         Owning user ID.
     * @param  string $storedName     The video's stored filename (used to derive the thumb name).
     * @param  bool   $isUploadedFile Use move_uploaded_file (true) or rename (false).
     * @return string|null
     */
    private static function saveThumbnail($sourcePath, $userId, $storedName, $isUploadedFile = true)
    {
        if (!file_exists($sourcePath) || filesize($sourcePath) > self::$maxThumbSize) {
            return null;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($sourcePath);

        if (!in_array($mime, self::$allowedThumbMimeTypes, true)) {
            return null;
        }

        $extMap    = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $ext       = $extMap[$mime];
        $thumbName = pathinfo($storedName, PATHINFO_FILENAME) . '_thumb.' . $ext;
        $userDir   = Config::get('PATH_USERVIDEOS') . $userId . DIRECTORY_SEPARATOR;

        if (!is_dir($userDir)) {
            if (!mkdir($userDir, 0750, true)) {
                return null;
            }
        }

        $targetPath = $userDir . $thumbName;
        $success    = $isUploadedFile
            ? move_uploaded_file($sourcePath, $targetPath)
            : rename($sourcePath, $targetPath);

        return $success ? $thumbName : null;
    }

    /**
     * Upload a video for the currently logged-in user.
     * Validates MIME type and file size, sanitizes the filename, moves the file
     * outside the webroot, and inserts a record into the database.
     *
     * @return bool
     */
    public static function uploadVideo()
    {
        if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] === UPLOAD_ERR_NO_FILE) {
            Session::add('feedback_negative', Text::get('FEEDBACK_VIDEO_UPLOAD_NO_FILE'));
            return false;
        }

        if ($_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
            Session::add('feedback_negative', Text::get('FEEDBACK_VIDEO_UPLOAD_FAILED'));
            return false;
        }

        if ($_FILES['video_file']['size'] > self::$maxFileSize) {
            Session::add('feedback_negative', Text::get('FEEDBACK_VIDEO_UPLOAD_TOO_BIG'));
            return false;
        }

        // Verify MIME type from file content (never trust $_FILES['type'])
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($_FILES['video_file']['tmp_name']);
        if (!in_array($mime, self::$allowedMimeTypes)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_VIDEO_UPLOAD_WRONG_TYPE'));
            return false;
        }

        $title       = $_POST['video_title'] ?? '';
        $description = $_POST['video_description'] ?? '';
        Filter::XSSFilter($title);
        Filter::XSSFilter($description);
        $title       = trim($title);
        $description = trim($description);
        $userId      = Session::get('user_id');

        if (empty($title)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_VIDEO_UPLOAD_NO_TITLE'));
            return false;
        }

        // Sanitize filename and generate a unique stored name
        $sanitized  = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['video_file']['name']));
        $storedName = time() . '_' . $sanitized;

        // Ensure user directory exists
        $userDir = Config::get('PATH_USERVIDEOS') . $userId . '/';
        if (!is_dir($userDir)) {
            if (!mkdir($userDir, 0750, true)) {
                Session::add('feedback_negative', Text::get('FEEDBACK_VIDEO_FOLDER_NOT_WRITABLE'));
                return false;
            }
        }

        if (!is_writable($userDir)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_VIDEO_FOLDER_NOT_WRITABLE'));
            return false;
        }

        // Move file to its final location
        $targetPath = $userDir . $storedName;
        if (!move_uploaded_file($_FILES['video_file']['tmp_name'], $targetPath)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_VIDEO_UPLOAD_FAILED'));
            return false;
        }

        // Handle optional thumbnail upload (non-fatal: proceed without it on failure)
        $thumbName = null;
        if (isset($_FILES['video_thumbnail']) && $_FILES['video_thumbnail']['error'] === UPLOAD_ERR_OK) {
            $thumbName = self::saveThumbnail($_FILES['video_thumbnail']['tmp_name'], $userId, $storedName, true);
        }

        // Insert record into database
        $mysqli   = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql      = "INSERT INTO videos (user_id, title, description, thumbnail, file_name, mime_type, file_size)
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt     = $mysqli->prepare($sql);
        if (!$stmt) {
            unlink($targetPath);
            Session::add('feedback_negative', Text::get('FEEDBACK_VIDEO_UPLOAD_FAILED'));
            return false;
        }

        $fileSize = (int) $_FILES['video_file']['size'];
        $stmt->bind_param("isssssi", $userId, $title, $description, $thumbName, $storedName, $mime, $fileSize);
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            unlink($targetPath);
            Session::add('feedback_negative', Text::get('FEEDBACK_VIDEO_UPLOAD_FAILED'));
            return false;
        }

        Session::add('feedback_positive', Text::get('FEEDBACK_VIDEO_UPLOAD_SUCCESSFUL'));
        return true;
    }

    /**
     * Get all videos owned by the currently logged-in user, newest first.
     *
     * @return array
     */
    public static function getMyVideos()
    {
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "SELECT video_id, user_id, title, description, thumbnail, file_name, mime_type, file_size,
                          is_published, created_at
                   FROM videos
                   WHERE user_id = ?
                   ORDER BY created_at DESC";
        $stmt   = $mysqli->prepare($sql);
        $userId = Session::get('user_id');
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows   = [];
        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Get all published videos across all users, newest first.
     * Joins with users table to include the owner's username.
     *
     * @return array
     */
    public static function getPublishedVideos()
    {
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "SELECT v.video_id, v.user_id, v.title, v.description, v.thumbnail, v.file_name, v.mime_type,
                          v.file_size, v.created_at, u.user_name
                   FROM videos v
                   JOIN users u ON v.user_id = u.user_id
                   WHERE v.is_published = 1
                   ORDER BY v.created_at DESC";
        $stmt   = $mysqli->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows   = [];
        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Search published videos by title or description.
     * Uses parameterized LIKE to prevent SQL injection.
     *
     * @param  string $query  The search term.
     * @return array
     */
    public static function searchPublishedVideos($query)
    {
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "SELECT v.video_id, v.user_id, v.title, v.description, v.thumbnail, v.file_name, v.mime_type,
                          v.file_size, v.created_at, u.user_name
                   FROM videos v
                   JOIN users u ON v.user_id = u.user_id
                   WHERE v.is_published = 1
                     AND (v.title LIKE CONCAT('%', ?, '%') OR v.description LIKE CONCAT('%', ?, '%'))
                   ORDER BY v.created_at DESC";
        $stmt   = $mysqli->prepare($sql);
        $stmt->bind_param("ss", $query, $query);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows   = [];
        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Get a single video record by its ID.
     *
     * @param  int $videoId
     * @return object|null
     */
    public static function getVideoById($videoId)
    {
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "SELECT video_id, user_id, title, description, thumbnail, file_name, mime_type, file_size,
                          is_published, created_at
                   FROM videos
                   WHERE video_id = ?
                   LIMIT 1";
        $stmt   = $mysqli->prepare($sql);
        $stmt->bind_param("i", $videoId);
        $stmt->execute();
        return $stmt->get_result()->fetch_object();
    }

    /**
     * Delete a video owned by the currently logged-in user.
     * Removes the physical file, cleans up the user directory if empty,
     * and deletes the DB row.
     *
     * @param  int $videoId
     * @return bool
     */
    public static function deleteVideo($videoId)
    {
        $userId = Session::get('user_id');

        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "SELECT video_id, user_id, file_name, thumbnail FROM videos WHERE video_id = ? AND user_id = ? LIMIT 1";
        $stmt   = $mysqli->prepare($sql);
        $stmt->bind_param("ii", $videoId, $userId);
        $stmt->execute();
        $video  = $stmt->get_result()->fetch_object();

        if (!$video) {
            Session::add('feedback_negative', Text::get('FEEDBACK_VIDEO_DELETE_FAILED'));
            return false;
        }

        // Delete physical file
        $filePath = Config::get('PATH_USERVIDEOS') . $userId . '/' . $video->file_name;
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete thumbnail file if present
        if (!empty($video->thumbnail)) {
            $thumbPath = Config::get('PATH_USERVIDEOS') . $userId . '/' . $video->thumbnail;
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
        }

        // Remove user directory if now empty
        $userDir = Config::get('PATH_USERVIDEOS') . $userId . '/';
        if (is_dir($userDir) && count(scandir($userDir)) === 2) {
            rmdir($userDir);
        }

        // Delete DB row
        $sql2  = "DELETE FROM videos WHERE video_id = ? AND user_id = ? LIMIT 1";
        $stmt2 = $mysqli->prepare($sql2);
        $stmt2->bind_param("ii", $videoId, $userId);
        $stmt2->execute();

        if ($stmt2->affected_rows !== 1) {
            Session::add('feedback_negative', Text::get('FEEDBACK_VIDEO_DELETE_FAILED'));
            return false;
        }

        Session::add('feedback_positive', Text::get('FEEDBACK_VIDEO_DELETE_SUCCESSFUL'));
        return true;
    }

    /**
     * Toggle the is_published flag of a video owned by the currently logged-in user.
     *
     * @param  int $videoId
     * @return bool
     */
    public static function togglePublished($videoId)
    {
        $userId = Session::get('user_id');
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "UPDATE videos SET is_published = 1 - is_published WHERE video_id = ? AND user_id = ? LIMIT 1";
        $stmt   = $mysqli->prepare($sql);
        $stmt->bind_param("ii", $videoId, $userId);
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            Session::add('feedback_negative', Text::get('FEEDBACK_VIDEO_PUBLISH_FAILED'));
            return false;
        }

        Session::add('feedback_positive', Text::get('FEEDBACK_VIDEO_PUBLISH_TOGGLED'));
        return true;
    }

    /**
     * Receive one chunk of a chunked video upload, append it to a temp file,
     * and on the final chunk assemble + validate + move + insert the DB record.
     *
     * @return array  ['status' => 'ok'|'done'|'error', ...]
     */
    public static function uploadChunk()
    {
        $userId = Session::get('user_id');

        // ── validate numeric inputs ──────────────────────────────────────────
        $chunkIndex  = filter_input(INPUT_POST, 'chunkIndex',  FILTER_VALIDATE_INT);
        $totalChunks = filter_input(INPUT_POST, 'totalChunks', FILTER_VALIDATE_INT);
        $totalSize   = filter_input(INPUT_POST, 'totalSize',   FILTER_VALIDATE_INT);

        if ($chunkIndex === false || $chunkIndex === null ||
            $totalChunks === false || $totalChunks === null || $totalChunks < 1 ||
            $totalSize   === false || $totalSize   === null || $totalSize   < 1) {
            return ['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_CHUNK_INVALID')];
        }

        // ── sanitize uploadUuid (alphanumeric + hyphens only – prevents path traversal) ──
        $rawUuid    = filter_input(INPUT_POST, 'uploadUuid') ?? '';
        $uploadUuid = preg_replace('/[^a-zA-Z0-9-]/', '', $rawUuid);
        if (empty($uploadUuid)) {
            return ['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_CHUNK_INVALID')];
        }

        // ── validate chunk file ──────────────────────────────────────────────
        if (!isset($_FILES['file_chunk']) || $_FILES['file_chunk']['error'] !== UPLOAD_ERR_OK) {
            return ['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_CHUNK_INVALID')];
        }

        // ── on first chunk: validate title + total size ──────────────────────
        if ($chunkIndex === 0) {
            $title = trim(filter_input(INPUT_POST, 'video_title') ?? '');
            if (empty($title)) {
                return ['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_UPLOAD_NO_TITLE')];
            }
            if ($totalSize > self::$maxChunkedFileSize) {
                return ['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_UPLOAD_TOO_BIG_CHUNKED')];
            }
        }

        // ── temp file path ───────────────────────────────────────────────────
        $tmpDir  = Config::get('PATH_USERVIDEOS') . 'tmp' . DIRECTORY_SEPARATOR;
        $tmpFile = $tmpDir . $userId . '_' . $uploadUuid . '.tmp';

        if (!is_dir($tmpDir)) {
            if (!mkdir($tmpDir, 0750, true)) {
                return ['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_FOLDER_NOT_WRITABLE')];
            }
        }

        // On first chunk: save optional thumbnail to temp location for use on final chunk
        if ($chunkIndex === 0 && isset($_FILES['video_thumbnail']) && $_FILES['video_thumbnail']['error'] === UPLOAD_ERR_OK) {
            move_uploaded_file($_FILES['video_thumbnail']['tmp_name'], $tmpDir . $userId . '_' . $uploadUuid . '_thumb');
        }

        // ── write chunk ──────────────────────────────────────────────────────
        $mode = ($chunkIndex === 0) ? 'wb' : 'ab';
        $out  = fopen($tmpFile, $mode);
        if (!$out) {
            return ['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_CHUNK_WRITE_FAILED')];
        }
        $in = fopen($_FILES['file_chunk']['tmp_name'], 'rb');
        if (!$in) {
            fclose($out);
            return ['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_CHUNK_WRITE_FAILED')];
        }
        while ($buf = fread($in, 65536)) {
            fwrite($out, $buf);
        }
        fclose($in);
        fclose($out);

        // ── not the last chunk – return progress ─────────────────────────────
        if ($chunkIndex < $totalChunks - 1) {
            return ['status' => 'ok', 'chunk' => $chunkIndex];
        }

        // ══ FINAL CHUNK: assemble + validate + move + insert DB ══════════════

        // Validate assembled file size
        $assembledSize = filesize($tmpFile);
        if ($assembledSize > self::$maxChunkedFileSize) {
            unlink($tmpFile);
            return ['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_UPLOAD_TOO_BIG_CHUNKED')];
        }

        // Validate MIME type from assembled file content (never trust client)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpFile);
        if (!in_array($mime, self::$allowedMimeTypes, true)) {
            unlink($tmpFile);
            return ['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_UPLOAD_WRONG_TYPE')];
        }

        // Sanitize original filename and generate unique stored name
        $originalName = basename(filter_input(INPUT_POST, 'originalName') ?? 'video.mp4');
        $sanitized    = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $storedName   = time() . '_' . $sanitized;

        // Ensure user directory exists
        $userDir = Config::get('PATH_USERVIDEOS') . $userId . DIRECTORY_SEPARATOR;
        if (!is_dir($userDir)) {
            if (!mkdir($userDir, 0750, true)) {
                unlink($tmpFile);
                return ['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_FOLDER_NOT_WRITABLE')];
            }
        }

        $finalPath = $userDir . $storedName;

        // Atomic rename (same filesystem)
        if (!rename($tmpFile, $finalPath)) {
            unlink($tmpFile);
            return ['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_CHUNK_FINALIZE_FAILED')];
        }

        // Retrieve thumbnail saved on chunk 0 (if any) and move to final location
        $thumbName    = null;
        $tmpThumbFile = $tmpDir . $userId . '_' . $uploadUuid . '_thumb';
        if (file_exists($tmpThumbFile)) {
            $thumbName = self::saveThumbnail($tmpThumbFile, $userId, $storedName, false);
        }

        // Collect title / description (sent on every chunk)
        $title       = trim(filter_input(INPUT_POST, 'video_title') ?? '');
        $description = trim(filter_input(INPUT_POST, 'video_description') ?? '');
        Filter::XSSFilter($title);
        Filter::XSSFilter($description);

        if (empty($title)) {
            unlink($finalPath);
            return ['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_UPLOAD_NO_TITLE')];
        }

        // Insert DB record
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "INSERT INTO videos (user_id, title, description, thumbnail, file_name, mime_type, file_size)
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt   = $mysqli->prepare($sql);
        if (!$stmt) {
            unlink($finalPath);
            return ['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_CHUNK_FINALIZE_FAILED')];
        }

        $fileSize = (int) $assembledSize;
        $stmt->bind_param("isssssi", $userId, $title, $description, $thumbName, $storedName, $mime, $fileSize);
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            unlink($finalPath);
            return ['status' => 'error', 'message' => Text::get('FEEDBACK_VIDEO_CHUNK_FINALIZE_FAILED')];
        }

        return ['status' => 'done'];
    }

    /**
     * Cast or change a rating (like/dislike) for a video by the current user.
     * Re-clicking the same vote removes it (toggle off). Each user can only ever
     * have one row per video (enforced by the composite primary key).
     *
     * @param  int  $videoId
     * @param  bool $isLike   true = like, false = dislike
     * @return array  ['status' => 'ok', 'likes' => N, 'dislikes' => N, 'userVote' => 1|0|null]
     */
    public static function rate($videoId, $isLike)
    {
        $userId  = Session::get('user_id');
        $mysqli  = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $likeVal = $isLike ? 1 : 0;

        // Find existing vote
        $sql  = "SELECT is_like FROM video_ratings WHERE video_id = ? AND user_id = ? LIMIT 1";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("ii", $videoId, $userId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_object();

        if ($existing && (int) $existing->is_like === $likeVal) {
            // Same vote clicked again -> remove it (toggle off)
            $del = $mysqli->prepare("DELETE FROM video_ratings WHERE video_id = ? AND user_id = ? LIMIT 1");
            $del->bind_param("ii", $videoId, $userId);
            $del->execute();
        } else {
            // Insert new or switch existing vote
            $up = $mysqli->prepare(
                "INSERT INTO video_ratings (video_id, user_id, is_like) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE is_like = VALUES(is_like)"
            );
            $up->bind_param("iii", $videoId, $userId, $likeVal);
            $up->execute();
        }

        $counts = self::getRatingCounts($videoId);
        return [
            'status'   => 'ok',
            'likes'    => $counts['likes'],
            'dislikes' => $counts['dislikes'],
            'userVote' => self::getUserVote($videoId, $userId),
        ];
    }

    /**
     * Get like/dislike totals for a video.
     *
     * @param  int $videoId
     * @return array ['likes' => int, 'dislikes' => int]
     */
    public static function getRatingCounts($videoId)
    {
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "SELECT
                        SUM(CASE WHEN is_like = 1 THEN 1 ELSE 0 END) AS likes,
                        SUM(CASE WHEN is_like = 0 THEN 1 ELSE 0 END) AS dislikes
                   FROM video_ratings WHERE video_id = ?";
        $stmt   = $mysqli->prepare($sql);
        $stmt->bind_param("i", $videoId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_object();
        return [
            'likes'    => (int) ($row->likes ?? 0),
            'dislikes' => (int) ($row->dislikes ?? 0),
        ];
    }

    /**
     * Get the current user's vote on a video.
     *
     * @param  int $videoId
     * @param  int $userId
     * @return int|null  1 = like, 0 = dislike, null = no vote
     */
    public static function getUserVote($videoId, $userId)
    {
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "SELECT is_like FROM video_ratings WHERE video_id = ? AND user_id = ? LIMIT 1";
        $stmt   = $mysqli->prepare($sql);
        $stmt->bind_param("ii", $videoId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_object();
        return $row ? (int) $row->is_like : null;
    }
}