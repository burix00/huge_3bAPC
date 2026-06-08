<div class="container">
    <h1>Public Gallery</h1>
    <div class="box">

        <?php $this->renderFeedbackMessages(); ?>

        <?php if ($this->public_files) { ?>
            <div class="gallery-grid">
                <?php foreach ($this->public_files as $file) { ?>
                    <div class="gallery-tile">

                        <!-- Thumbnail / inline preview -->
                        <a href="<?php echo Config::get('URL'); ?>gallery/serve/<?php echo (int) $file->file_id; ?>"
                           target="_blank" class="gallery-thumb-link">
                            <img src="<?php echo Config::get('URL'); ?>gallery/serve/<?php echo (int) $file->file_id; ?>"
                                 alt="<?php echo htmlspecialchars($file->original_name, ENT_QUOTES, 'UTF-8'); ?>"
                                 class="gallery-thumb" />
                        </a>

                        <!-- File info -->
                        <div class="gallery-info">
                            <span class="gallery-filename" title="<?php echo htmlspecialchars($file->original_name, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($file->original_name, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <span class="gallery-meta">
                                by <strong><?php echo htmlspecialchars($file->user_name, ENT_QUOTES, 'UTF-8'); ?></strong>
                                &middot; <?php echo number_format($file->file_size / 1024, 1); ?> KB
                                &middot; <?php echo (int) $file->downloads; ?> download<?php echo $file->downloads == 1 ? '' : 's'; ?>
                            </span>
                        </div>

                        <!-- Download action -->
                        <div class="gallery-actions">
                            <a href="<?php echo Config::get('URL'); ?>gallery/download/<?php echo (int) $file->file_id; ?>"
                               class="btn btn-small">Download</a>
                        </div>

                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <p>No public images have been shared yet.</p>
        <?php } ?>

        <?php if (Session::userIsLoggedIn()) { ?>
            <hr />
            <p><a href="<?php echo Config::get('URL'); ?>gallery/index">Back to My Gallery</a></p>
        <?php } ?>

    </div>
</div>
