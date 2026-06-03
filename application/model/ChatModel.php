<?php

class ChatModel
{
    /**
     * Find existing chat between two users, or create a new one
     * @param int $receiver_id
     * @return int|false the chat_id or false on failure
     */
    public static function getOrCreateChat($receiver_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();
        $user_id = Session::get('user_id');

        $u1 = min($user_id, $receiver_id);
        $u2 = max($user_id, $receiver_id);

        $sql = "SELECT chat_id FROM chat WHERE user1_id = :u1 AND user2_id = :u2 LIMIT 1";
        $query = $database->prepare($sql);
        $query->execute(array(':u1' => $u1, ':u2' => $u2));
        $chat = $query->fetch();

        if ($chat) {
            return $chat->chat_id;
        }

        $sql = "INSERT INTO chat (user1_id, user2_id) VALUES (:u1, :u2)";
        $query = $database->prepare($sql);
        $query->execute(array(':u1' => $u1, ':u2' => $u2));

        return $query->rowCount() == 1 ? $database->lastInsertId() : false;
    }

    /**
     * Get all messages in a chat between the logged-in user and another user
     * @param int $receiver_id the other user's id
     * @return array
     */
    public static function getMessages($receiver_id)
    {
        $chat_id = self::getOrCreateChat($receiver_id);
        if (!$chat_id) {
            return array();
        }

        $database = DatabaseFactory::getFactory()->getConnection();
        
        $sql = "CALL get_messages(:chat_id)";
        $query = $database->prepare($sql);
        
        $query->execute(array(':chat_id' => $chat_id));

        return $query->fetchAll();
    }

    /**
     * Send a message to another user
     * @param int $receiver_id
     * @param string $message_text
     * @return bool
     */
    public static function sendMessage($receiver_id, $message_text)
    {
        if (!$receiver_id || !$message_text || strlen(trim($message_text)) == 0) {
            return false;
        }

        $chat_id = self::getOrCreateChat($receiver_id);
        if (!$chat_id) {
            return false;
        }

        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "INSERT INTO messages (chat_id, sender_user_id, receiver_user_id, message_text)
                VALUES (:chat_id, :sender_id, :receiver_id, :message_text)";
        $query = $database->prepare($sql);
        $query->execute(array(
            ':chat_id'      => $chat_id,
            ':sender_id'    => Session::get('user_id'),
            ':receiver_id'  => $receiver_id,
            ':message_text' => strip_tags(trim($message_text))
        ));

        return $query->rowCount() == 1;
    }

    /**
     * Mark all messages from a sender as read
     * @param int $sender_id
     * @return bool
     */
    public static function markAsRead($sender_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "UPDATE messages SET is_read = 1
                WHERE sender_user_id = :sender_id AND receiver_user_id = :user_id AND is_read = 0";
        $query = $database->prepare($sql);
        $query->execute(array(
            ':sender_id' => $sender_id,
            ':user_id'   => Session::get('user_id')
        ));

        return $query->rowCount() > 0;
    }

    /**
     * Get unread message counts per user
     * @return array
     */
    public static function getUnreadCountsPerUser()
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "SELECT sender_user_id, COUNT(*) AS unread_count
                FROM messages
                WHERE receiver_user_id = :user_id AND is_read = 0
                GROUP BY sender_user_id";
        $query = $database->prepare($sql);
        $query->execute([':user_id' => Session::get('user_id')]);

        $rows = $query->fetchAll();
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row->sender_user_id] = $row->unread_count;
        }
        return $counts;
    }
}