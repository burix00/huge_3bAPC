CREATE TABLE IF NOT EXISTS `huge`.`chat` (
    `chat_id` int(11)  NOT NULL AUTO_INCREMENT,
    `sender_user_id` int(11) NOT NULL,
    `receiver_user_id` int(11)  NOT NULL,
    `message_text` text NOT NULL,
    `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`chat_id`),
    FOREIGN KEY (`sender_user_id`) REFERENCES `huge`.`users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_user_id`) REFERENCES `huge`.`users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;