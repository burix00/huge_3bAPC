<div class="container">
    <h1>My Gallery</h1>
    <div class="box">

        <?php $this->renderFeedbackMessages(); ?>

        <!-- Upload Form -->
        <h2>Upload Image</h2>
        <form method="post" action="<?php echo Config::get('URL'); ?>gallery/upload" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />
            <label for="gallery_file">Select image (JPEG, PNG or GIF, max 5 MB):</label>
            <input type="file" id="gallery_file" name="gallery_file" accept=".jpg,.jpeg,.png,.gif" />
            <input type="submit" value="Upload" />
        </form>

        <hr />

        <!-- File Grid -->
        <?php if ($this->my_files) { ?>
            <h2>My Images</h2>
            <div class="gallery-grid">
                <?php foreach ($this->my_files as $file) { ?>
                    <div class="gallery-tile <?php echo $file->shared ? 'gallery-tile--shared' : ''; ?>">

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
                                <?php echo number_format($file->file_size / 1024, 1); ?> KB &middot;
                                <?php echo (int) $file->downloads; ?> download<?php echo $file->downloads == 1 ? '' : 's'; ?>
                            </span>
                            <span class="gallery-shared-badge">
                                <?php echo $file->shared ? '&#128275; Public' : '&#128274; Private'; ?>
                            </span>
                        </div>

                        <!-- Actions -->
                        <div class="gallery-actions">

                            <!-- Download -->
                            <a href="<?php echo Config::get('URL'); ?>gallery/download/<?php echo (int) $file->file_id; ?>"
                               class="btn btn-small">Download</a>

                            <!-- Toggle Share -->
                            <form method="post"
                                  action="<?php echo Config::get('URL'); ?>gallery/toggleShare/<?php echo (int) $file->file_id; ?>"
                                  style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />
                                <input type="submit"
                                       value="<?php echo $file->shared ? 'Make Private' : 'Make Public'; ?>"
                                       class="btn btn-small <?php echo $file->shared ? 'btn-warning' : 'btn-success'; ?>" />
                            </form>

                            <!-- Delete -->
                            <form method="post"
                                  action="<?php echo Config::get('URL'); ?>gallery/delete/<?php echo (int) $file->file_id; ?>"
                                  style="display:inline;"
                                  onsubmit="return confirm('Delete \'<?php echo addslashes(htmlspecialchars($file->original_name, ENT_QUOTES, 'UTF-8')); ?>\'? This cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />
                                <input type="submit" value="Delete" class="btn btn-small btn-danger" />
                            </form>

                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <p>You have not uploaded any images yet.</p>
        <?php } ?>

        <!-- Link to public gallery -->
        <hr />
        <p>
            <a href="<?php echo Config::get('URL'); ?>gallery/publicGallery">View Public Gallery</a>
        </p>

    </div>
</div>
