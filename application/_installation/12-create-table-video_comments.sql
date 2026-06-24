CREATE TABLE IF NOT EXISTS `huge`.`video_comments` (
  `comment_id`   int(11) unsigned NOT NULL AUTO_INCREMENT,
  `video_id`     int(11)          NOT NULL,
  `user_id`      int(11)          NOT NULL,
  `comment_text` text             NOT NULL,
  `created_at`   datetime         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`comment_id`),
  KEY `video_id` (`video_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `comments_video_fk` FOREIGN KEY (`video_id`) REFERENCES `videos` (`video_id`) ON DELETE CASCADE,
  CONSTRAINT `comments_user_fk`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`user_id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
