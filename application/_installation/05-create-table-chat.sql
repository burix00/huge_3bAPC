CREATE TABLE IF NOT EXISTS `huge`.`chat` (
    `chat_id` int(11) NOT NULL AUTO_INCREMENT,
    `user1_id` int(11) NOT NULL,
    `user2_id` int(11) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`chat_id`),
    UNIQUE KEY `unique_chat` (`user1_id`, `user2_id`),
    FOREIGN KEY (`user1_id`) REFERENCES `huge`.`users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user2_id`) REFERENCES `huge`.`users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;