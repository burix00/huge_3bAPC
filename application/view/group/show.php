<?php // [KI-generiert – Claude Opus 4.7] ?>
<?php $currentUserId = (int)Session::get('user_id'); ?>
<?php
/* Deterministische Farbe pro Username (HSL via crc32). */
function group_chat_user_color($name) {
    $hue = crc32($name) % 360;
    if ($hue < 0) { $hue += 360; }
    return 'hsl(' . $hue . ', 60%, 35%)';
}
?>

<style>
/* [KI-generiert – Claude Opus 4.7] */
.group-chat-layout {
    display: flex;
    gap: 16px;
    align-items: flex-start;
}
.group-chat-main { flex: 3; min-width: 0; }
.group-chat-side { flex: 1; min-width: 200px; }

.group-chat-messages {
    height: 420px;
    overflow-y: auto;
    background: #ece5dd;
    border: 1px solid #ccc;
    padding: 10px;
    display: flex;
    flex-direction: column;
}
.message {
    max-width: 70%;
    margin: 4px 0;
    padding: 6px 10px;
    border-radius: 8px;
    word-wrap: break-word;
}
/* Eigene Nachrichten */
.message.own {
    text-align: right;
    background: #dcf8c6; /* WhatsApp-Grün */
    margin-left: auto;
}
/* Fremde Nachrichten */
.message.other {
    text-align: left;
    background: #ffffff;
    border: 1px solid #e0e0e0;
    margin-right: auto;
}
/* Username über fremden Nachrichten */
.message-sender {
    font-weight: bold;
    font-size: 0.8em;
    display: block;
    margin-bottom: 2px;
}
.message-time {
    display: block;
    font-size: 0.7em;
    color: #888;
    margin-top: 2px;
}
.group-chat-form { margin-top: 8px; display: flex; gap: 6px; }
.group-chat-form textarea { flex: 1; min-height: 40px; resize: vertical; }
.group-members-list { list-style: none; padding: 0; }
.group-members-list li {
    padding: 4px 6px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.group-admin-actions { margin-top: 10px; }
.group-admin-actions form { display: inline; }
</style>

<div class="container">
    <h1>Gruppe: <?php echo htmlspecialchars($this->group->name); ?></h1>
    <div class="box">
        <?php $this->renderFeedbackMessages(); ?>

        <div class="group-chat-layout">
            <!-- Chat-Hauptbereich -->
            <div class="group-chat-main">
                <div id="group-chat-messages" class="group-chat-messages"
                     data-group-id="<?php echo (int)$this->group->group_id; ?>"
                     data-csrf="<?php echo Csrf::makeToken(); ?>"
                     data-url="<?php echo Config::get('URL'); ?>"
                     data-current-user="<?php echo $currentUserId; ?>">
                    <?php if (empty($this->messages)) : ?>
                        <p id="group-chat-empty"><em>Noch keine Nachrichten. Sag Hallo!</em></p>
                    <?php else : ?>
                        <?php foreach ($this->messages as $m) : ?>
                            <?php $isOwn = ((int)$m->sender_id === $currentUserId); ?>
                            <div class="message <?php echo $isOwn ? 'own' : 'other'; ?>"
                                 data-message-id="<?php echo (int)$m->message_id; ?>">
                                <?php if (!$isOwn) : ?>
                                    <span class="message-sender"
                                          style="color: <?php echo group_chat_user_color($m->sender_name); ?>">
                                        <?php echo htmlspecialchars($m->sender_name); ?>
                                    </span>
                                <?php endif; ?>
                                <?php echo nl2br(htmlspecialchars($m->message)); ?>
                                <span class="message-time"><?php echo htmlspecialchars($m->sent_at); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form id="group-chat-form" class="group-chat-form"
                      method="post"
                      action="<?php echo Config::get('URL'); ?>group/sendMessage/<?php echo (int)$this->group->group_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />
                    <textarea name="message" placeholder="Nachricht schreiben…" required></textarea>
                    <input type="submit" value="Senden" />
                </form>
            </div>

            <!-- Mitglieder-Sidebar -->
            <div class="group-chat-side">
                <h3>Mitglieder (<?php echo count($this->members); ?>)</h3>
                <ul class="group-members-list">
                    <?php foreach ($this->members as $member) : ?>
                        <li>
                            <span style="color: <?php echo group_chat_user_color($member->user_name); ?>">
                                <?php echo $member->role === 'admin' ? '⭐ ' : ''; ?>
                                <?php echo htmlspecialchars($member->user_name); ?>
                            </span>
                            <?php if ($this->isAdmin && (int)$member->user_id !== $currentUserId) : ?>
                                <form method="post"
                                      action="<?php echo Config::get('URL'); ?>group/removeMember/<?php echo (int)$this->group->group_id; ?>"
                                      onsubmit="return confirm('Mitglied wirklich entfernen?');"
                                      style="margin:0;">
                                    <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />
                                    <input type="hidden" name="user_id" value="<?php echo (int)$member->user_id; ?>" />
                                    <button type="submit" title="Entfernen"
                                            style="background:none; border:none; cursor:pointer; color:#c00;">✕</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($this->isAdmin) : ?>
                    <h4>Mitglied hinzufügen</h4>
                    <form method="post"
                          action="<?php echo Config::get('URL'); ?>group/addMember/<?php echo (int)$this->group->group_id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />
                        <input type="text" name="user_name" placeholder="Benutzername" required />
                        <input type="submit" value="Hinzufügen" />
                    </form>
                <?php endif; ?>

                <div class="group-admin-actions">
                    <form method="post"
                          action="<?php echo Config::get('URL'); ?>group/leave/<?php echo (int)$this->group->group_id; ?>"
                          onsubmit="return confirm('Gruppe wirklich verlassen?');">
                        <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />
                        <input type="submit" value="Gruppe verlassen" />
                    </form>
                    <?php if ($this->isAdmin) : ?>
                        <form method="post"
                              action="<?php echo Config::get('URL'); ?>group/delete/<?php echo (int)$this->group->group_id; ?>"
                              onsubmit="return confirm('Gruppe inkl. aller Nachrichten endgültig löschen?');">
                            <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />
                            <input type="submit" value="Gruppe löschen"
                                   style="background:#c00; color:#fff;" />
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// [KI-generiert – Claude Opus 4.7]
(function () {
    var box       = document.getElementById('group-chat-messages');
    var form      = document.getElementById('group-chat-form');
    if (!box) { return; }

    var groupId   = box.dataset.groupId;
    var baseUrl   = box.dataset.url;
    var csrfToken = box.dataset.csrf;
    var currentUserId = parseInt(box.dataset.currentUser, 10);

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function userColor(name) {
        // Match server-side crc32 modulo for visual consistency (close enough).
        var h = 0;
        for (var i = 0; i < name.length; i++) {
            h = (h * 31 + name.charCodeAt(i)) >>> 0;
        }
        return 'hsl(' + (h % 360) + ', 60%, 35%)';
    }
    function getLastMessageId() {
        var nodes = box.querySelectorAll('.message[data-message-id]');
        if (!nodes.length) { return 0; }
        return parseInt(nodes[nodes.length - 1].dataset.messageId, 10) || 0;
    }
    function scrollToBottom() { box.scrollTop = box.scrollHeight; }

    function appendMessage(m) {
        var empty = document.getElementById('group-chat-empty');
        if (empty) { empty.remove(); }

        var div = document.createElement('div');
        div.className = 'message ' + (m.is_own ? 'own' : 'other');
        div.dataset.messageId = m.message_id;

        var html = '';
        if (!m.is_own) {
            html += '<span class="message-sender" style="color:' + userColor(m.sender_name) + '">' +
                    escapeHtml(m.sender_name) + '</span>';
        }
        html += escapeHtml(m.message).replace(/\n/g, '<br>');
        html += '<span class="message-time">' + escapeHtml(m.sent_at) + '</span>';
        div.innerHTML = html;
        box.appendChild(div);
    }

    function poll() {
        var lastId = getLastMessageId();
        fetch(baseUrl + 'group/getNewMessages/' + groupId + '?last_id=' + lastId, {
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.ok || !data.messages || !data.messages.length) { return; }
            var atBottom = (box.scrollTop + box.clientHeight >= box.scrollHeight - 40);
            data.messages.forEach(appendMessage);
            if (atBottom) { scrollToBottom(); }
        })
        .catch(function () { /* ignore transient errors */ });
    }

    var ajaxEnabled = true;
    form.addEventListener('submit', function (e) {
        if (!ajaxEnabled) { return; } // Fallback: klassisches Formular abschicken lassen
        e.preventDefault();
        var ta = form.querySelector('textarea[name=message]');
        var text = (ta.value || '').trim();
        if (!text) { return; }

        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('message', text);
        fd.append('ajax', '1');

        fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    ta.value = '';
                    poll();
                } else {
                    ajaxEnabled = false;
                    form.submit();
                }
            })
            .catch(function () { ajaxEnabled = false; form.submit(); });
    });

    scrollToBottom();
    setInterval(poll, 3000);
})();
</script>
