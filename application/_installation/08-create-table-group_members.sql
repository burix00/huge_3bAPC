-- [KI-generiert – Claude Opus 4.7]
CREATE TABLE IF NOT EXISTS `huge`.`group_members` (
    `member_id` int(11) NOT NULL AUTO_INCREMENT,
    `group_id` int(11) NOT NULL,
    `user_id` int(11) NOT NULL,
    `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `role` ENUM('admin','member') NOT NULL DEFAULT 'member',
    PRIMARY KEY (`member_id`),
    UNIQUE KEY `unique_group_member` (`group_id`, `user_id`),
    FOREIGN KEY (`group_id`) REFERENCES `huge`.`groups`(`group_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `huge`.`users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
