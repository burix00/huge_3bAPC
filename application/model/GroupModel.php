<?php
// [KI-generiert – Claude Opus 4.7]

/**
 * GroupModel
 * Datenzugriffsschicht für Gruppenchats: Gruppen, Mitglieder und Nachrichten.
 * Folgt den Konventionen von ChatModel/UserModel (statische Methoden, PDO via
 * DatabaseFactory, vorbereitete Statements).
 */
class GroupModel
{
    /**
     * Legt eine neue Gruppe an und trägt den Ersteller als Admin ein.
     * @param string $name
     * @param int $creatorUserId
     * @return int|false neue group_id oder false
     */
    public static function createGroup($name, $creatorUserId)
    {
        $name = trim(strip_tags($name));
        if ($name === '' || strlen($name) > 64) {
            Session::add('feedback_negative', 'Gruppenname ungültig (1–64 Zeichen).');
            return false;
        }

        $database = DatabaseFactory::getFactory()->getConnection();
        try {
            $database->beginTransaction();

            $q = $database->prepare("INSERT INTO `groups` (name, created_by) VALUES (:name, :uid)");
            $q->execute([':name' => $name, ':uid' => (int)$creatorUserId]);
            $groupId = (int)$database->lastInsertId();

            $q = $database->prepare("INSERT INTO group_members (group_id, user_id, role)
                                     VALUES (:gid, :uid, 'admin')");
            $q->execute([':gid' => $groupId, ':uid' => (int)$creatorUserId]);

            $database->commit();
            return $groupId;
        } catch (Exception $e) {
            $database->rollBack();
            Session::add('feedback_negative', 'Gruppe konnte nicht erstellt werden.');
            return false;
        }
    }

    /**
     * Löscht eine Gruppe. Nur Admin darf dies tun. CASCADE entfernt Mitglieder + Nachrichten.
     */
    public static function deleteGroup($groupId, $userId)
    {
        if (!self::isAdmin($groupId, $userId)) {
            Session::add('feedback_negative', 'Nur Admins dürfen die Gruppe löschen.');
            return false;
        }
        $database = DatabaseFactory::getFactory()->getConnection();
        $q = $database->prepare("DELETE FROM `groups` WHERE group_id = :gid");
        $q->execute([':gid' => (int)$groupId]);
        return $q->rowCount() === 1;
    }

    /**
     * Fügt einen User einer Gruppe als 'member' hinzu (idempotent dank UNIQUE).
     */
    public static function addMember($groupId, $userId)
    {
        $database = DatabaseFactory::getFactory()->getConnection();
        $q = $database->prepare("INSERT IGNORE INTO group_members (group_id, user_id, role)
                                 VALUES (:gid, :uid, 'member')");
        $q->execute([':gid' => (int)$groupId, ':uid' => (int)$userId]);
        return $q->rowCount() === 1;
    }

    /**
     * Entfernt einen User aus einer Gruppe.
     */
    public static function removeMember($groupId, $userId)
    {
        $database = DatabaseFactory::getFactory()->getConnection();
        $q = $database->prepare("DELETE FROM group_members
                                 WHERE group_id = :gid AND user_id = :uid");
        $q->execute([':gid' => (int)$groupId, ':uid' => (int)$userId]);
        return $q->rowCount() === 1;
    }

    /**
     * Liefert alle Gruppen, in denen der User Mitglied ist, inkl. Mitgliederanzahl.
     */
    public static function getGroupsForUser($userId)
    {
        $database = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT g.group_id, g.name, g.created_by, g.created_at, gm.role,
                       (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = g.group_id)
                           AS member_count
                FROM `groups` g
                INNER JOIN group_members gm ON gm.group_id = g.group_id
                WHERE gm.user_id = :uid
                ORDER BY g.created_at DESC";
        $q = $database->prepare($sql);
        $q->execute([':uid' => (int)$userId]);
        $rows = $q->fetchAll();
        foreach ($rows as $r) {
            $r->name = Filter::XSSFilter($r->name);
        }
        return $rows;
    }

    /**
     * Liefert alle Mitglieder einer Gruppe inkl. user_name und Rolle.
     */
    public static function getMembersOfGroup($groupId)
    {
        $database = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT u.user_id, u.user_name, u.user_has_avatar, gm.role, gm.joined_at
                FROM group_members gm
                INNER JOIN users u ON u.user_id = gm.user_id
                WHERE gm.group_id = :gid
                ORDER BY gm.role = 'admin' DESC, u.user_name ASC";
        $q = $database->prepare($sql);
        $q->execute([':gid' => (int)$groupId]);
        $rows = $q->fetchAll();
        foreach ($rows as $r) {
            $r->user_name = Filter::XSSFilter($r->user_name);
        }
        return $rows;
    }

    /**
     * Speichert eine Nachricht. Sender muss Mitglied sein.
     * @return int|false neue message_id oder false
     */
    public static function sendMessage($groupId, $senderId, $msg)
    {
        $msg = trim(strip_tags($msg));
        if ($msg === '') {
            return false;
        }
        if (!self::isMember($groupId, $senderId)) {
            return false;
        }
        $database = DatabaseFactory::getFactory()->getConnection();
        $q = $database->prepare("INSERT INTO group_messages (group_id, sender_id, message)
                                 VALUES (:gid, :sid, :msg)");
        $q->execute([
            ':gid' => (int)$groupId,
            ':sid' => (int)$senderId,
            ':msg' => $msg,
        ]);
        return $q->rowCount() === 1 ? (int)$database->lastInsertId() : false;
    }

    /**
     * Lädt die neuesten $limit Nachrichten und liefert sie in chronologischer Reihenfolge zurück.
     */
    public static function getMessages($groupId, $limit = 50)
    {
        $limit = max(1, min(500, (int)$limit));
        $database = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT m.message_id, m.group_id, m.sender_id, m.message, m.sent_at,
                       u.user_name AS sender_name
                FROM group_messages m
                INNER JOIN users u ON u.user_id = m.sender_id
                WHERE m.group_id = :gid
                ORDER BY m.message_id DESC
                LIMIT $limit";
        $q = $database->prepare($sql);
        $q->execute([':gid' => (int)$groupId]);
        $rows = array_reverse($q->fetchAll());
        foreach ($rows as $r) {
            $r->message     = Filter::XSSFilter($r->message);
            $r->sender_name = Filter::XSSFilter($r->sender_name);
        }
        return $rows;
    }

    /**
     * Liefert alle Nachrichten mit message_id > $lastMessageId (für AJAX-Polling).
     */
    public static function getMessagesAfter($groupId, $lastMessageId)
    {
        $database = DatabaseFactory::getFactory()->getConnection();
        $sql = "SELECT m.message_id, m.group_id, m.sender_id, m.message, m.sent_at,
                       u.user_name AS sender_name
                FROM group_messages m
                INNER JOIN users u ON u.user_id = m.sender_id
                WHERE m.group_id = :gid AND m.message_id > :last
                ORDER BY m.message_id ASC";
        $q = $database->prepare($sql);
        $q->execute([':gid' => (int)$groupId, ':last' => (int)$lastMessageId]);
        $rows = $q->fetchAll();
        foreach ($rows as $r) {
            $r->message     = Filter::XSSFilter($r->message);
            $r->sender_name = Filter::XSSFilter($r->sender_name);
        }
        return $rows;
    }

    /**
     * Prüft, ob ein User Admin der Gruppe ist.
     */
    public static function isAdmin($groupId, $userId)
    {
        $database = DatabaseFactory::getFactory()->getConnection();
        $q = $database->prepare("SELECT role FROM group_members
                                 WHERE group_id = :gid AND user_id = :uid LIMIT 1");
        $q->execute([':gid' => (int)$groupId, ':uid' => (int)$userId]);
        $row = $q->fetch();
        return $row && $row->role === 'admin';
    }

    /**
     * Prüft, ob ein User Mitglied der Gruppe ist.
     */
    public static function isMember($groupId, $userId)
    {
        $database = DatabaseFactory::getFactory()->getConnection();
        $q = $database->prepare("SELECT 1 FROM group_members
                                 WHERE group_id = :gid AND user_id = :uid LIMIT 1");
        $q->execute([':gid' => (int)$groupId, ':uid' => (int)$userId]);
        return (bool)$q->fetch();
    }

    /**
     * Anzahl Admins in der Gruppe (für "letzter Admin verlässt"-Schutz).
     */
    public static function countAdmins($groupId)
    {
        $database = DatabaseFactory::getFactory()->getConnection();
        $q = $database->prepare("SELECT COUNT(*) AS c FROM group_members
                                 WHERE group_id = :gid AND role = 'admin'");
        $q->execute([':gid' => (int)$groupId]);
        $r = $q->fetch();
        return $r ? (int)$r->c : 0;
    }

    /**
     * Liefert Stammdaten einer Gruppe (oder null).
     */
    public static function getGroup($groupId)
    {
        $database = DatabaseFactory::getFactory()->getConnection();
        $q = $database->prepare("SELECT group_id, name, created_by, created_at
                                 FROM `groups` WHERE group_id = :gid LIMIT 1");
        $q->execute([':gid' => (int)$groupId]);
        $row = $q->fetch();
        if ($row) {
            $row->name = Filter::XSSFilter($row->name);
        }
        return $row ?: null;
    }

    /**
     * Liefert die user_id zum gegebenen Benutzernamen oder null.
     */
    public static function getUserIdByName($userName)
    {
        $userName = trim($userName);
        if ($userName === '') {
            return null;
        }
        $database = DatabaseFactory::getFactory()->getConnection();
        $q = $database->prepare("SELECT user_id FROM users WHERE user_name = :name LIMIT 1");
        $q->execute([':name' => $userName]);
        $row = $q->fetch();
        return $row ? (int)$row->user_id : null;
    }
}
