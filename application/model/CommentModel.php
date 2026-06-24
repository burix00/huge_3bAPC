<?php

/**
 * CommentModel
 * Handles all database operations for video comments.
 */
class CommentModel
{
    /** Maximum comment length */
    private static $maxLength = 1000;

    /**
     * Add a comment to a video for the currently logged-in user.
     *
     * @param  int    $videoId
     * @param  string $text
     * @return bool
     */
    public static function addComment($videoId, $text)
    {
        $text = trim((string) $text);

        if ($text === '') {
            Session::add('feedback_negative', Text::get('FEEDBACK_COMMENT_EMPTY'));
            return false;
        }
        if (mb_strlen($text) > self::$maxLength) {
            Session::add('feedback_negative', Text::get('FEEDBACK_COMMENT_TOO_LONG'));
            return false;
        }

        $userId = Session::get('user_id');
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "INSERT INTO video_comments (video_id, user_id, comment_text) VALUES (?, ?, ?)";
        $stmt   = $mysqli->prepare($sql);
        if (!$stmt) {
            Session::add('feedback_negative', Text::get('FEEDBACK_COMMENT_ADD_FAILED'));
            return false;
        }
        $stmt->bind_param("iis", $videoId, $userId, $text);
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            Session::add('feedback_negative', Text::get('FEEDBACK_COMMENT_ADD_FAILED'));
            return false;
        }

        Session::add('feedback_positive', Text::get('FEEDBACK_COMMENT_ADD_SUCCESSFUL'));
        return true;
    }

    /**
     * Get all comments for a video, newest first, joined with the author's username.
     *
     * @param  int $videoId
     * @return array
     */
    public static function getCommentsByVideo($videoId)
    {
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "SELECT c.comment_id, c.video_id, c.user_id, c.comment_text, c.created_at, u.user_name
                   FROM video_comments c
                   JOIN users u ON c.user_id = u.user_id
                   WHERE c.video_id = ?
                   ORDER BY c.created_at DESC";
        $stmt   = $mysqli->prepare($sql);
        $stmt->bind_param("i", $videoId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows   = [];
        while ($row = $result->fetch_object()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Delete a comment. Allowed only for the comment's author.
     *
     * @param  int $commentId
     * @return bool
     */
    public static function deleteComment($commentId)
    {
        $userId = Session::get('user_id');
        $mysqli = DatabaseFactoryMySqli::getFactory()->getConnectionMySqli();
        $sql    = "DELETE FROM video_comments WHERE comment_id = ? AND user_id = ? LIMIT 1";
        $stmt   = $mysqli->prepare($sql);
        $stmt->bind_param("ii", $commentId, $userId);
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            Session::add('feedback_negative', Text::get('FEEDBACK_COMMENT_DELETE_FAILED'));
            return false;
        }

        Session::add('feedback_positive', Text::get('FEEDBACK_COMMENT_DELETE_SUCCESSFUL'));
        return true;
    }
}
