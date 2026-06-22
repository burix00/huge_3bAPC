<div class="container">
    <h1>Public Videos</h1>
    <div class="box">

        <?php $this->renderFeedbackMessages(); ?>

        <?php if ($this->published_videos) { ?>
            <div class="video-grid">
                <?php foreach ($this->published_videos as $video) { ?>
                    <div class="video-tile">

                        <div class="video-info">
                            <strong>
                                <a href="<?php echo Config::get('URL'); ?>video/viewVideo/<?php echo (int) $video->video_id; ?>">
                                    <?php echo htmlspecialchars($video->title, ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </strong>
                            <?php if (!empty($video->description)) { ?>
                                <p class="video-description">
                                    <?php echo htmlspecialchars($video->description, ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php } ?>
                            <span class="video-meta">
                                by <strong><?php echo htmlspecialchars($video->user_name, ENT_QUOTES, 'UTF-8'); ?></strong>
                                &middot;
                                <?php echo number_format($video->file_size / 1048576, 1); ?> MB
                                &middot;
                                <?php echo htmlspecialchars($video->created_at, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>

                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <p>No public videos have been shared yet.</p>
        <?php } ?>

        <?php if (Session::userIsLoggedIn()) { ?>
            <hr />
            <p><a href="<?php echo Config::get('URL'); ?>video/index">Back to My Videos</a></p>
        <?php } ?>

    </div>
</div>
