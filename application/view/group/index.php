<?php // [KI-generiert – Claude Opus 4.7] ?>
<div class="container">
    <h1>Gruppenchats</h1>
    <div class="box">
        <?php $this->renderFeedbackMessages(); ?>

        <h2>Neue Gruppe erstellen</h2>
        <form method="post" action="<?php echo Config::get('URL'); ?>group/create">
            <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />
            <label for="group_name">Gruppenname</label>
            <input type="text" id="group_name" name="group_name"
                   maxlength="64" required autocomplete="off" />
            <input type="submit" value="Gruppe erstellen" />
        </form>
    </div>

    <div class="box">
        <h2>Meine Gruppen</h2>
        <?php if (empty($this->groups)) : ?>
            <p>Du bist noch in keiner Gruppe.</p>
        <?php else : ?>
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:6px;">Name</th>
                        <th style="text-align:left; padding:6px;">Mitglieder</th>
                        <th style="text-align:left; padding:6px;">Rolle</th>
                        <th style="text-align:left; padding:6px;">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->groups as $g) : ?>
                    <tr>
                        <td style="padding:6px;"><?php echo htmlspecialchars($g->name); ?></td>
                        <td style="padding:6px;"><?php echo (int)$g->member_count; ?></td>
                        <td style="padding:6px;">
                            <?php echo $g->role === 'admin' ? '⭐ Admin' : 'Mitglied'; ?>
                        </td>
                        <td style="padding:6px;">
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
</div>
