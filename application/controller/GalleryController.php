<?php

/**
 * GalleryController
 * Handles all routes for the private image gallery feature.
 */
class GalleryController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // Auth is checked individually per method — publicGallery() is intentionally open.
    }

    /**
     * Show the current user's gallery with upload form.
     */
    public function index()
    {
        Auth::checkAuthentication();
        $this->View->render('gallery/index', array(
            'my_files' => GalleryModel::getMyFiles()
        ));
    }

    /**
     * POST: Process a file upload.
     */
    public function upload()
    {
        Auth::checkAuthentication();

        if (!Csrf::isTokenValid()) {
            LoginModel::logout();
            Redirect::home();
            exit();
        }

        GalleryModel::uploadFile();
        Redirect::to('gallery');
    }

    /**
     * Stream a file inline (used as image source in <img> tags).
     * Access allowed for: file owner OR file is shared (shared = 1).
     *
     * @param int $fileId
     */
    public function serve($fileId)
    {
        Auth::checkAuthentication();

        $file = GalleryModel::getFileById((int) $fileId);

        if (!$file) {
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_FILE_NOT_FOUND'));
            Redirect::to('gallery');
            exit();
        }

        $userId = Session::get('user_id');
        if ($file->owner_id != $userId && $file->shared != 1) {
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_ACCESS_DENIED'));
            Redirect::to('gallery');
            exit();
        }

        $filePath = Config::get('PATH_USERPICTURES') . $file->owner_id . '/' . $file->file_name;
        if (!file_exists($filePath)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_FILE_NOT_FOUND'));
            Redirect::to('gallery');
            exit();
        }

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . $file->original_name . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private, max-age=3600');
        readfile($filePath);
        exit();
    }

    /**
     * Send a file as a download attachment.
     * Access allowed for: file owner OR file is shared (shared = 1).
     * Also increments the download counter.
     *
     * @param int $fileId
     */
    public function download($fileId)
    {
        Auth::checkAuthentication();

        $file = GalleryModel::getFileById((int) $fileId);

        if (!$file) {
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_FILE_NOT_FOUND'));
            Redirect::to('gallery');
            exit();
        }

        $userId = Session::get('user_id');
        if ($file->owner_id != $userId && $file->shared != 1) {
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_ACCESS_DENIED'));
            Redirect::to('gallery');
            exit();
        }

        $filePath = Config::get('PATH_USERPICTURES') . $file->owner_id . '/' . $file->file_name;
        if (!file_exists($filePath)) {
            Session::add('feedback_negative', Text::get('FEEDBACK_GALLERY_FILE_NOT_FOUND'));
            Redirect::to('gallery');
            exit();
        }

        GalleryModel::incrementDownloadCount((int) $fileId);

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $file->original_name . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: private');
        readfile($filePath);
        exit();
    }

    /**
     * POST: Delete a file owned by the current user.
     *
     * @param int $fileId
     */
    public function delete($fileId)
    {
        Auth::checkAuthentication();

        if (!Csrf::isTokenValid()) {
            LoginModel::logout();
            Redirect::home();
            exit();
        }

        GalleryModel::deleteFile((int) $fileId);
        Redirect::to('gallery');
    }

    /**
     * POST: Toggle the shared flag of a file owned by the current user.
     *
     * @param int $fileId
     */
    public function toggleShare($fileId)
    {
        Auth::checkAuthentication();

        if (!Csrf::isTokenValid()) {
            LoginModel::logout();
            Redirect::home();
            exit();
        }

        GalleryModel::toggleShared((int) $fileId);
        Redirect::to('gallery');
    }

    /**
     * Public gallery — shows all files marked as shared.
     * No authentication required; accessible to all visitors.
     */
    public function publicGallery()
    {
        $this->View->render('gallery/public', array(
            'public_files' => GalleryModel::getPublicFiles()
        ));
    }
}
