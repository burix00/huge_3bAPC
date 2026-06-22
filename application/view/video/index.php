<div class="container">
    <h1>My Videos</h1>
    <div class="box">

        <?php $this->renderFeedbackMessages(); ?>

        <p>
            <a href="<?php echo Config::get('URL'); ?>video/upload">Upload Video</a>
            &nbsp;&middot;&nbsp;
            <a href="<?php echo Config::get('URL'); ?>video/publicVideos">View Public Videos</a>
        </p>

        <?php if ($this->my_files) { ?>
            <div class="video-grid">
                <?php foreach ($this->my_files as $video) { ?>
                    <div class="video-tile">

                        <div class="video-info">
                            <strong>
                                <a href="<?php echo Config::get('URL'); ?>video/viewVideo/<?php echo (int) $video->video_id; ?>">
                                    <?php echo htmlspecialchars($video->title, ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </strong>
                            <span class="video-meta">
                                <?php echo number_format($video->file_size / 1048576, 1); ?> MB
                                &middot;
                                <?php echo htmlspecialchars($video->created_at, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <span class="video-status">
                                <?php echo $video->is_published ? '&#128275; Public' : '&#128274; Private'; ?>
                            </span>
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