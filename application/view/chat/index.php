<div class="container">
    <h1>ChatController/index</h1>
    <div class="box">

        <!-- echo out the system feedback (error and success messages) -->
        <?php $this->renderFeedbackMessages(); ?>

        <h3>Chat with users!</h3>
        
        <div>
            <table class="overview-table" id="chatTable">
                <thead>
                <tr>
                    <td>Id</td>
                    <td>Avatar</td>
                    <td>Username</td>
                    <td>Link to user's profile</td>
                    <td>Chat</td>
                </tr>
                </thead>
                <?php foreach ($this->users as $user) { ?>
                    <tr class="<?= ($user->user_active == 0 ? 'inactive' : 'active'); ?>">
                        <td><?= $user->user_id; ?></td>
                        <td class="avatar">
                            <?php if (isset($user->user_avatar_link)) { ?>
                                <img src="<?= $user->user_avatar_link; ?>" />
                            <?php } ?>
                        </td>
                        <td>
                            <?= $user->user_name; ?>
                            <?php if (!empty($this->unread_counts[$user->user_id])): ?>
                                <span style="background:red; color:white; border-radius:50%; padding:2px 7px; font-size:12px; margin-left:5px;">
                                    <?= $this->unread_counts[$user->user_id]; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= Config::get('URL') . 'profile/showProfile/' . $user->user_id; ?>">Profile</a>
                        </td>
                        <td>
                            <a href="<?= Config::get('URL') . 'chat/showChat/' . $user->user_id; ?>">Chat</a>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    </div>

    <?php /* [KI-generiert – Claude Opus 4.7] Gruppenchat-Integration im Messenger */ ?>
    <div class="box">
        <h3>Meine Gruppenchats</h3>
        <?php if (empty($this->groups)) : ?>
            <p><em>Du bist noch in keiner Gruppe.</em></p>
        <?php else : ?>
            <table class="overview-table">
                <thead>
                    <tr>
                        <td>Name</td>
                        <td>Mitglieder</td>
                        <td>Rolle</td>
                        <td>Aktion</td>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->groups as $g) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($g->name); ?></td>
                        <td><?php echo (int)$g->member_count; ?></td>
                        <td><?php echo $g->role === 'admin' ? '⭐ Admin' : 'Mitglied'; ?></td>
                        <td>
                            <a href="<?php echo Config::get('URL'); ?>group/view/<?php echo (int)$g->group_id; ?>">
                                Öffnen
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="box">
        <h3>Neue Gruppe erstellen</h3>
        <form method="post" action="<?php echo Config::get('URL'); ?>group/create">
            <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />

            <p>
                <label for="group_name"><strong>Gruppenname</strong></label><br>
                <input type="text" id="group_name" name="group_name"
                       maxlength="64" required autocomplete="off"
                       style="width: 320px;" />
            </p>

            <p>
                <label for="group_members_select"><strong>Mitglieder hinzufügen</strong> (optional)</label><br>
                <em style="font-size: 0.9em; color: #666;">
                    Mit Strg/Cmd + Klick mehrere Benutzer auswählen. Du bist automatisch dabei.
                </em>
            </p>
            <input type="text" id="group_members_filter"
                   placeholder="Benutzer suchen…"
                   style="width: 320px; margin-bottom: 4px;"
                   onkeyup="(function(q){
                       q = q.toLowerCase();
                       var sel = document.getElementById('group_members_select');
                       for (var i = 0; i < sel.options.length; i++) {
                           var t = sel.options[i].text.toLowerCase();
                           sel.options[i].hidden = (q && t.indexOf(q) === -1);
                       }
                   })(this.value)" />
            <br>
            <select id="group_members_select" name="members[]" multiple size="8"
                    style="width: 320px;">
                <?php foreach ($this->all_users as $u) : ?>
                    <option value="<?php echo (int)$u->user_id; ?>">
                        <?php echo htmlspecialchars($u->user_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <p>
                <input type="submit" value="Gruppe erstellen" />
            </p>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#chatTable').DataTable();
    });
</script>
