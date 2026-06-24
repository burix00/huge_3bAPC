CREATE TABLE IF NOT EXISTS `huge`.`video_ratings` (
  `video_id`   int(11)    NOT NULL,
  `user_id`    int(11)    NOT NULL,
  `is_like`    tinyint(1) NOT NULL COMMENT '1 = like, 0 = dislike',
  `created_at` datetime   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`video_id`, `user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ratings_video_fk` FOREIGN KEY (`video_id`) REFERENCES `videos` (`video_id`) ON DELETE CASCADE,
  CONSTRAINT `ratings_user_fk`  FOREIGN KEY (`user_id`)  REFERENCES `users`  (`user_id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
