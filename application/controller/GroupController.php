<?php
// [KI-generiert – Claude Opus 4.7]

/**
 * GroupController
 * Steuert die Gruppenchat-Funktionalität. Komplett auth-gated.
 *
 * URL-Schema (HUGE konventionsbasiert, kein routes.php):
 *   /group/index
 *   /group/create                       (POST)
 *   /group/view/{groupId}
 *   /group/sendMessage/{groupId}        (POST, optional ajax=1)
 *   /group/getNewMessages/{groupId}?last_id=N   (JSON)
 *   /group/addMember/{groupId}          (POST, admin)
 *   /group/removeMember/{groupId}       (POST, admin)
 *   /group/delete/{groupId}             (POST, admin)
 *   /group/leave/{groupId}              (POST)
 */
class GroupController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        Auth::checkAuthentication();
    }

    /**
     * Übersicht aller Gruppen des eingeloggten Users + Erstell-Formular.
     */
    public function index()
    {
        // [KI-generiert – Claude Opus 4.7] Gruppenübersicht ist jetzt Teil von /chat/index
        Redirect::to('chat/index');
    }

    /**
     * Legt eine neue Gruppe an (POST + CSRF).
     */
    public function create()
    {
        if (!$this->guardPostCsrf()) {
            return;
        }
        $name    = Request::post('group_name');
        $userId  = Session::get('user_id');
        // [KI-generiert – Claude Opus 4.7] optionale Mitglieder-Vorauswahl per Multi-Select
        $members = Request::post('members');
        $newId   = GroupModel::createGroup($name, $userId);
        if ($newId) {
            if (is_array($members)) {
                $added = 0;
                foreach ($members as $memberId) {
                    $memberId = (int)$memberId;
                    if ($memberId > 0 && $memberId !== (int)$userId) {
                        if (GroupModel::addMember($newId, $memberId)) {
                            $added++;
                        }
                    }
                }
                Session::add('feedback_positive',
                    'Gruppe „' . htmlspecialchars($name) . '" erstellt' .
                    ($added ? ' mit ' . $added . ' Mitglied' . ($added === 1 ? '' : 'ern') . '.' : '.')
                );
            } else {
                Session::add('feedback_positive', 'Gruppe „' . htmlspecialchars($name) . '" wurde erstellt.');
            }
            Redirect::to('group/view/' . (int)$newId);
            return;
        }
        Redirect::to('chat/index');
    }

    /**
     * Zeigt eine Gruppenchat-Ansicht inkl. Nachrichten und Mitgliederliste.
     */
    public function view($groupId = null)
    {
        $groupId = (int)$groupId;
        $userId  = Session::get('user_id');
        if (!$groupId || !GroupModel::isMember($groupId, $userId)) {
            Session::add('feedback_negative', 'Kein Zugriff auf diese Gruppe.');
            Redirect::to('group/index');
            return;
        }
        $this->View->render('group/show', [
            'group'    => GroupModel::getGroup($groupId),
            'messages' => GroupModel::getMessages($groupId, 100),
            'members'  => GroupModel::getMembersOfGroup($groupId),
            'isAdmin'  => GroupModel::isAdmin($groupId, $userId),
        ]);
    }

    /**
     * Nachricht senden (POST + CSRF). Mit ?ajax=1 wird JSON zurückgegeben.
     */
    public function sendMessage($groupId = null)
    {
        if (!$this->guardPostCsrf(true)) {
            return;
        }
        $groupId = (int)$groupId;
        $userId  = Session::get('user_id');
        $text    = Request::post('message');
        $isAjax  = (bool)Request::post('ajax');

        if (!GroupModel::isMember($groupId, $userId)) {
            if ($isAjax) {
                $this->View->renderJSON(['ok' => false, 'error' => 'not_a_member']);
                return;
            }
            Redirect::to('group/index');
            return;
        }

        $msgId = GroupModel::sendMessage($groupId, $userId, $text);

        if ($isAjax) {
            $this->View->renderJSON([
                'ok'         => (bool)$msgId,
                'message_id' => $msgId ?: null,
            ]);
            return;
        }
        Redirect::to('group/view/' . $groupId);
    }

    /**
     * JSON-Endpoint für AJAX-Polling.
     * GET /group/getNewMessages/{groupId}?last_id=N
     */
    public function getNewMessages($groupId = null)
    {
        $groupId = (int)$groupId;
        $userId  = Session::get('user_id');
        $lastId  = (int)Request::get('last_id');

        if (!$groupId || !GroupModel::isMember($groupId, $userId)) {
            $this->View->renderJSON(['ok' => false, 'error' => 'not_a_member', 'messages' => []]);
            return;
        }

        $rows = GroupModel::getMessagesAfter($groupId, $lastId);
        $out  = [];
        foreach ($rows as $r) {
            $out[] = [
                'message_id'  => (int)$r->message_id,
                'sender_id'   => (int)$r->sender_id,
                'sender_name' => $r->sender_name,
                'message'     => $r->message,
                'sent_at'     => $r->sent_at,
                'is_own'      => ((int)$r->sender_id === (int)$userId),
            ];
        }
        $this->View->renderJSON(['ok' => true, 'messages' => $out]);
    }

    /**
     * Mitglied per Username hinzufügen (Admin-only).
     */
    public function addMember($groupId = null)
    {
        if (!$this->guardPostCsrf()) {
            return;
        }
        $groupId = (int)$groupId;
        $userId  = Session::get('user_id');
        if (!GroupModel::isAdmin($groupId, $userId)) {
            Session::add('feedback_negative', 'Nur Admins dürfen Mitglieder hinzufügen.');
            Redirect::to('group/view/' . $groupId);
            return;
        }
        $username = Request::post('user_name');
        $targetId = GroupModel::getUserIdByName($username);
        if (!$targetId) {
            Session::add('feedback_negative', 'Benutzer nicht gefunden.');
        } elseif (GroupModel::addMember($groupId, $targetId)) {
            Session::add('feedback_positive', 'Mitglied hinzugefügt.');
        } else {
            Session::add('feedback_negative', 'Benutzer ist bereits Mitglied.');
        }
        Redirect::to('group/view/' . $groupId);
    }

    /**
     * Mitglied entfernen (Admin-only, nicht sich selbst – dafür gibt es leave).
     */
    public function removeMember($groupId = null)
    {
        if (!$this->guardPostCsrf()) {
            return;
        }
        $groupId  = (int)$groupId;
        $userId   = Session::get('user_id');
        $targetId = (int)Request::post('user_id');
        if (!GroupModel::isAdmin($groupId, $userId)) {
            Session::add('feedback_negative', 'Nur Admins dürfen Mitglieder entfernen.');
        } elseif ($targetId === (int)$userId) {
            Session::add('feedback_negative', 'Bitte „Gruppe verlassen" benutzen, um dich selbst zu entfernen.');
        } elseif (GroupModel::removeMember($groupId, $targetId)) {
            Session::add('feedback_positive', 'Mitglied entfernt.');
        } else {
            Session::add('feedback_negative', 'Mitglied konnte nicht entfernt werden.');
        }
        Redirect::to('group/view/' . $groupId);
    }

    /**
     * Gruppe löschen (Admin-only).
     */
    public function delete($groupId = null)
    {
        if (!$this->guardPostCsrf()) {
            return;
        }
        $groupId = (int)$groupId;
        $userId  = Session::get('user_id');
        if (GroupModel::deleteGroup($groupId, $userId)) {
            Session::add('feedback_positive', 'Gruppe gelöscht.');
            Redirect::to('group/index');
            return;
        }
        Redirect::to('group/view/' . $groupId);
    }

    /**
     * Gruppe verlassen. Letzter Admin darf nicht verlassen, solange weitere Mitglieder existieren.
     */
    public function leave($groupId = null)
    {
        if (!$this->guardPostCsrf()) {
            return;
        }
        $groupId = (int)$groupId;
        $userId  = Session::get('user_id');
        if (!GroupModel::isMember($groupId, $userId)) {
            Redirect::to('group/index');
            return;
        }
        if (GroupModel::isAdmin($groupId, $userId) && GroupModel::countAdmins($groupId) === 1) {
            $members = GroupModel::getMembersOfGroup($groupId);
            if (count($members) > 1) {
                Session::add('feedback_negative',
                    'Du bist der letzte Admin. Bitte zuerst einen anderen Admin bestimmen oder die Gruppe löschen.');
                Redirect::to('group/view/' . $groupId);
                return;
            }
        }
        if (GroupModel::removeMember($groupId, $userId)) {
            Session::add('feedback_positive', 'Du hast die Gruppe verlassen.');
        }
        Redirect::to('group/index');
    }

    /**
     * Gemeinsamer POST + CSRF-Guard.
     * @param bool $allowAjaxJsonError bei AJAX JSON-Fehler statt Redirect ausgeben
     */
    private function guardPostCsrf($allowAjaxJsonError = false)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Redirect::to('group/index');
            return false;
        }
        if (!Csrf::isTokenValid()) {
            if ($allowAjaxJsonError && Request::post('ajax')) {
                $this->View->renderJSON(['ok' => false, 'error' => 'csrf']);
                return false;
            }
            LoginModel::logout();
            Redirect::home();
            return false;
        }
        return true;
    }
}
