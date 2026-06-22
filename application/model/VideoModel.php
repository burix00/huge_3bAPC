<?php

/**
 * VideoModel
 * Handles all database and filesystem operations for user videos.
 */
class VideoModel
{
    /** Allowed MIME types for upload */
    private static $allowedMimeTypes = ['video/mp4', 'video/webm', 'video/ogg'];

    /** Maximum upload size: 200 MB */
    private static $maxFileSize = 209715200;

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

        // Insert record into database
        $mysqli   = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql      = "INSERT INTO videos (user_id, title, description, file_name, mime_type, file_size)
                     VALUES (?, ?, ?, ?, ?, ?)";
        $stmt     = $mysqli->prepare($sql);
        if (!$stmt) {
            unlink($targetPath);
            Session::add('feedback_negative', Text::get('FEEDBACK_VIDEO_UPLOAD_FAILED'));
            return false;
        }

        $fileSize = (int) $_FILES['video_file']['size'];
        $stmt->bind_param("issssi", $userId, $title, $description, $storedName, $mime, $fileSize);
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
        $sql    = "SELECT video_id, user_id, title, description, file_name, mime_type, file_size,
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
        $sql    = "SELECT v.video_id, v.user_id, v.title, v.description, v.file_name, v.mime_type,
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
     * Get a single video record by its ID.
     *
     * @param  int $videoId
     * @return object|null
     */
    public static function getVideoById($videoId)
    {
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "SELECT video_id, user_id, title, description, file_name, mime_type, file_size,
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
        $sql    = "SELECT video_id, user_id, file_name FROM videos WHERE video_id = ? AND user_id = ? LIMIT 1";
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
}