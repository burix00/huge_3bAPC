<?php

/**
 * GalleryModel
 * Handles all database and filesystem operations for the private image gallery.
 */
class GalleryModel
{
    /** Allowed MIME types for upload */
    private static $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];

    /** Maximum upload size: 5 MB */
    private static $maxFileSize = 5242880;

    /**
     * Upload a file for the currently logged-in user.
     * Validates MIME type, file size, sanitizes the filename, moves the file outside
     * the webroot, and inserts a record into the database.
     *
     * @return bool
     */
    public static function uploadFile()
    {
        // 1. Check that a file was actually submitted
        if (!isset($_FILES['gallery_file']) || $_FILES['gallery_file']['error'] === UPLOAD_ERR_NO_FILE) {
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_UPLOAD_NO_FILE'));
            return false;
        }

        // 2. Check for upload errors
        if ($_FILES['gallery_file']['error'] !== UPLOAD_ERR_OK) {
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_UPLOAD_FAILED'));
            return false;
        }

        // 3. Check file size
        if ($_FILES['gallery_file']['size'] > self::$maxFileSize) {
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_UPLOAD_TOO_BIG'));
            return false;
        }

        // 4. Verify MIME type from file content (never trust $_FILES['type'])
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($_FILES['gallery_file']['tmp_name']);
        if (!in_array($mime, self::$allowedMimeTypes)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_UPLOAD_WRONG_TYPE'));
            return false;
        }

        // 5. Sanitize filename and generate a unique stored name
        $sanitized    = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['gallery_file']['name']));
        $storedName   = time() . '_' . $sanitized;
        $originalName = $sanitized;
        $userId       = Session::get('user_id');

        // 6. Ensure user directory exists
        $userDir = Config::get('PATH_USERPICTURES') . $userId . '/';
        if (!is_dir($userDir)) {
            if (!mkdir($userDir, 0750, true)) {
                Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_FOLDER_NOT_WRITABLE'));
                return false;
            }
        }

        if (!is_writable($userDir)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_FOLDER_NOT_WRITABLE'));
            return false;
        }

        // 7. Move file to its final location
        $targetPath = $userDir . $storedName;
        if (!move_uploaded_file($_FILES['gallery_file']['tmp_name'], $targetPath)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_UPLOAD_FAILED'));
            return false;
        }

        // 8. Insert record into database
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "INSERT INTO files (owner_id, file_name, original_name, file_size, downloads, shared, upload_timestamp)
                   VALUES (?, ?, ?, ?, 0, 0, ?)";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            // Rollback: remove the already-moved file
            unlink($targetPath);
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_UPLOAD_FAILED'));
            return false;
        }
        $fileSize  = (int) $_FILES['gallery_file']['size'];
        $timestamp = time();
        $stmt->bind_param("issii", $userId, $storedName, $originalName, $fileSize, $timestamp);
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            unlink($targetPath);
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_UPLOAD_FAILED'));
            return false;
        }

        Session::add('feedback_positive', Text::get('FEEDBACK_GALLERY_UPLOAD_SUCCESSFUL'));
        return true;
    }

    /**
     * Get all files owned by the currently logged-in user, newest first.
     *
     * @return array of objects
     */
    public static function getMyFiles()
    {
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "SELECT file_id, owner_id, file_name, original_name, file_size, downloads, shared, upload_timestamp
                   FROM files
                   WHERE owner_id = ?
                   ORDER BY upload_timestamp DESC";
        $stmt = $mysqli->prepare($sql);
        $userId = Session::get('user_id');
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Get all publicly shared files across all users, newest first.
     * Joins with users table to include the owner's username.
     *
     * @return array of objects
     */
    public static function getPublicFiles()
    {
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "SELECT f.file_id, f.owner_id, f.file_name, f.original_name, f.file_size, f.downloads,
                          f.upload_timestamp, u.user_name
                   FROM files f
                   JOIN users u ON f.owner_id = u.user_id
                   WHERE f.shared = 1
                   ORDER BY f.upload_timestamp DESC";
        $stmt = $mysqli->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Get a single file record by its ID.
     *
     * @param  int $fileId
     * @return object|null
     */
    public static function getFileById($fileId)
    {
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "SELECT file_id, owner_id, file_name, original_name, file_size, downloads, shared, upload_timestamp
                   FROM files
                   WHERE file_id = ?
                   LIMIT 1";
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param("i", $fileId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_object();
    }

    /**
     * Delete a file owned by the currently logged-in user.
     * Removes the physical file, cleans up the user directory if empty, and deletes the DB row.
     *
     * @param  int $fileId
     * @return bool
     */
    public static function deleteFile($fileId)
    {
        $userId = Session::get('user_id');

        // Fetch record with owner check
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "SELECT file_id, owner_id, file_name FROM files WHERE file_id = ? AND owner_id = ? LIMIT 1";
        $stmt   = $mysqli->prepare($sql);
        $stmt->bind_param("ii", $fileId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $file   = $result->fetch_object();

        if (!$file) {
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_DELETE_FAILED'));
            return false;
        }

        // Delete physical file
        $filePath = Config::get('PATH_USERPICTURES') . $userId . '/' . $file->file_name;
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Remove user directory if now empty
        $userDir = Config::get('PATH_USERPICTURES') . $userId . '/';
        if (is_dir($userDir) && count(scandir($userDir)) === 2) { // only '.' and '..'
            rmdir($userDir);
        }

        // Delete DB row
        $sql2  = "DELETE FROM files WHERE file_id = ? AND owner_id = ? LIMIT 1";
        $stmt2 = $mysqli->prepare($sql2);
        $stmt2->bind_param("ii", $fileId, $userId);
        $stmt2->execute();

        if ($stmt2->affected_rows !== 1) {
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_DELETE_FAILED'));
            return false;
        }

        Session::add('feedback_positive', Text::get('FEEDBACK_GALLERY_DELETE_SUCCESSFUL'));
        return true;
    }

    /**
     * Toggle the shared flag of a file owned by the currently logged-in user.
     * Uses atomic SQL expression (1 - shared) to avoid race conditions.
     *
     * @param  int $fileId
     * @return bool
     */
    public static function toggleShared($fileId)
    {
        $userId = Session::get('user_id');
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "UPDATE files SET shared = 1 - shared WHERE file_id = ? AND owner_id = ? LIMIT 1";
        $stmt   = $mysqli->prepare($sql);
        $stmt->bind_param("ii", $fileId, $userId);
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_SHARE_FAILED'));
            return false;
        }

        Session::add('feedback_positive', Text::get('FEEDBACK_GALLERY_SHARE_TOGGLED'));
        return true;
    }

    /**
     * Increment the download counter for a file.
     *
     * @param  int $fileId
     * @return void
     */
    public static function incrementDownloadCount($fileId)
    {
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "UPDATE files SET downloads = downloads + 1 WHERE file_id = ? LIMIT 1";
        $stmt   = $mysqli->prepare($sql);
        $stmt->bind_param("i", $fileId);
        $stmt->execute();
    }
}
