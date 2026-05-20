<?php

class ChatModel
{
    /**
    * Get all messages in a chat between two logged in users
    * @param int $receiver_id the other users's id
    * @return array
    */
    public static function getMessages($receiver_id)
    {
        $database = DatabaseFactory::getFactory()->getConnection();

        $sql = "SELECT chat_id, sender_user_id, receiver_user_id, message_text, sent_at, is_read

    }

}