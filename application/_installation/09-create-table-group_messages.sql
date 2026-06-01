-- [KI-generiert – Claude Opus 4.7]
CREATE TABLE IF NOT EXISTS `huge`.`group_messages` (
    `message_id` int(11) NOT NULL AUTO_INCREMENT,
    `group_id` int(11) NOT NULL,
    `sender_id` int(11) NOT NULL,
    `message` text NOT NULL,
    `sent_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`message_id`),
    KEY `idx_group_message` (`group_id`, `message_id`),
    FOREIGN KEY (`group_id`) REFERENCES `huge`.`groups`(`group_id`) ON DELETE CASCADE,
    FOREIGN KEY (`sender_id`) REFERENCES `huge`.`users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
