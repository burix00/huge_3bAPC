<div class="container">
    <h1>My Videos</h1>
    <div class="box">

        <?php $this->renderFeedbackMessages(); ?>

        <p>
            <a href="<?php echo Config::get('URL'); ?>video/upload" class="btn">Upload Video</a>
            <a href="<?php echo Config::get('URL'); ?>video/publicVideos" class="btn">View Public Videos</a>
        </p>

        <?php if ($this->my_files) { ?>
            <div class="video-grid">
                <?php foreach ($this->my_files as $video) { ?>
                    <div class="video-card">
                        <a href="<?php echo Config::get('URL'); ?>video/viewVideo/<?php echo (int) $video->video_id; ?>" class="video-thumbnail-link">
                            <div class="video-thumbnail">
                                <?php if (!empty($video->thumbnail)) { ?>
                                    <img src="<?php echo Config::get('URL'); ?>video/thumbnail/<?php echo (int) $video->video_id; ?>"
                                         alt="<?php echo htmlspecialchars($video->title, ENT_QUOTES, 'UTF-8'); ?>" />
                                <?php } else { ?>
                                    <span class="video-thumbnail-placeholder">&#9654;</span>
                                <?php } ?>
                            </div>
                        </a>
                        <div class="video-card-body">
                            <div class="video-card-title">
                                <a href="<?php echo Config::get('URL'); ?>video/viewVideo/<?php echo (int) $video->video_id; ?>">
                                    <?php echo htmlspecialchars($video->title, ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </div>
                            <div class="video-meta">
                                <?php echo number_format($video->file_size / 1048576, 1); ?> MB
                                &middot;
                                <?php echo htmlspecialchars($video->created_at, ENT_QUOTES, 'UTF-8'); ?>
                                &middot;
                                <?php echo $video->is_published ? '&#128275; Public' : '&#128274; Private'; ?>
                            </div>
                        </div>
                        <div class="video-actions">
                            <!-- Toggle Published -->
                            <form method="post"
                                  action="<?php echo Config::get('URL'); ?>video/togglePublished/<?php echo (int) $video->video_id; ?>"
                                  style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />
                                <input type="submit"
                                       value="<?php echo $video->is_published ? 'Make Private' : 'Make Public'; ?>"
                                       class="btn btn-small <?php echo $video->is_published ? 'btn-warning' : 'btn-success'; ?>" />
                            </form>

                            <!-- Delete -->
                            <form method="post"
                                  action="<?php echo Config::get('URL'); ?>video/deleteVideo/<?php echo (int) $video->video_id; ?>"
                                  style="display:inline;"
                                  onsubmit="return confirm('Delete \'<?php echo addslashes(htmlspecialchars($video->title, ENT_QUOTES, 'UTF-8')); ?>\'? This cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />
                                <input type="submit" value="Delete" class="btn btn-small btn-danger" />
                            </form>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <p>You have not uploaded any videos yet.</p>
        <?php } ?>

    </div>
</div>




</div>