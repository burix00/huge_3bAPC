CREATE TABLE IF NOT EXISTS `huge`.`files` (
 `file_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
 `owner_id` int(11) NOT NULL,
 `file_name` varchar(255) NOT NULL COMMENT 'stored filename on disk',
 `original_name` varchar(255) NOT NULL COMMENT 'original filename for display',
 `file_size` int(11) unsigned NOT NULL DEFAULT 0,
 `downloads` int(11) unsigned NOT NULL DEFAULT 0,
 `shared` tinyint(1) unsigned NOT NULL DEFAULT 0,
 `upload_timestamp` bigint(20) unsigned NOT NULL,
 PRIMARY KEY (`file_id`),
 KEY `owner_id` (`owner_id`),
 CONSTRAINT `files_owner_fk` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='user gallery files';