<div class="container">
    <h1><?php echo htmlspecialchars($this->video->title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="box">

        <?php $this->renderFeedbackMessages(); ?>

        <video controls style="width:100%; max-width:960px;">
            <source src="<?php echo Config::get('URL'); ?>video/stream/<?php echo (int) $this->video->video_id; ?>"
                    type="<?php echo htmlspecialchars($this->video->mime_type, ENT_QUOTES, 'UTF-8'); ?>">
            Your browser does not support the video tag.
        </video>

        <?php if (!empty($this->video->description)) { ?>
            <p><?php echo htmlspecialchars($this->video->description, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php } ?>

        <p class="video-meta">
            Uploaded: <?php echo htmlspecialchars($this->video->created_at, ENT_QUOTES, 'UTF-8'); ?>
            &middot;
            <?php echo number_format($this->video->file_size / 1048576, 1); ?> MB
        </p>

        <hr />
        <p>
            <a href="<?php echo Config::get('URL'); ?>video/index">My Videos</a>
            &nbsp;&middot;&nbsp;
            <a href="<?php echo Config::get('URL'); ?>video/publicVideos">Public Videos</a>
        </p>

    </div>
</div>
