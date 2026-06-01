-- [KI-generiert – Claude Opus 4.7]
--
-- Gruppenchat – ER-Diagramm (ASCII)
--
--   +-------------+        +------------------+        +-------------------+
--   |   users     |        |     groups       |        |  group_messages   |
--   |-------------|        |------------------|        |-------------------|
--   | user_id PK  |<--+    | group_id PK      |<-+     | message_id PK     |
--   | user_name   |   |    | name             |  |     | group_id  FK -----+--+
--   | ...         |   +----| created_by FK    |  |     | sender_id FK -----+--+--> users.user_id
--   +-------------+   |    | created_at       |  |     | message           |  |
--                     |    +------------------+  |     | sent_at           |  |
--                     |             ^            |     +-------------------+  |
--                     |             |            |                            |
--                     |    +------------------+  |                            |
--                     |    |  group_members   |  |                            |
--                     |    |------------------|  |                            |
--                     |    | member_id PK     |  |                            |
--                     +----| user_id   FK     |  |                            |
--                          | group_id  FK ----+--+                            |
--                          | joined_at        |                               |
--                          | role (admin|...) |                               |
--                          | UNIQUE(grp,user) |                               |
--                          +------------------+                               |
--                                                                             |
-- Beziehungen:
--   users 1 ── n groups          (groups.created_by → users.user_id)
--   groups 1 ── n group_members  (group_members.group_id → groups.group_id)
--   users 1 ── n group_members   (group_members.user_id → users.user_id)
--   groups 1 ── n group_messages (group_messages.group_id → groups.group_id)
--   users 1 ── n group_messages  (group_messages.sender_id → users.user_id)
--
CREATE TABLE IF NOT EXISTS `huge`.`groups` (
    `group_id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(64) NOT NULL,
    `created_by` int(11) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`group_id`),
    FOREIGN KEY (`created_by`) REFERENCES `huge`.`users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
