CREATE TABLE IF NOT EXISTS `huge`.`messages` (
    `message_id` int(11) NOT NULL AUTO_INCREMENT,
    `chat_id` int(11) NOT NULL,
    `sender_user_id` int(11) NOT NULL,
    `receiver_user_id` int(11) NOT NULL,
    `message_text` text NOT NULL,
    `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`message_id`),
    FOREIGN KEY (`chat_id`) REFERENCES `huge`.`chat`(`chat_id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_user_id`) REFERENCES `huge`.`users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_user_id`) REFERENCES `huge`.`users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;