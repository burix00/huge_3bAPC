<div class="container">
    <h1>Public Videos</h1>
    <div class="box">

        <?php $this->renderFeedbackMessages(); ?>

        <form method="GET" action="<?php echo Config::get('URL'); ?>video/publicVideos" class="video-search-form">
            <input type="text"
                   name="search"
                   placeholder="Search videos by title or description..."
                   value="<?php echo htmlspecialchars($this->search_query, ENT_QUOTES, 'UTF-8'); ?>"
                   class="video-search-input" />
            <button type="submit" class="btn">Search</button>
            <?php if ($this->search_query !== '') { ?>
                <a href="<?php echo Config::get('URL'); ?>video/publicVideos" class="btn btn-secondary">Clear</a>
            <?php } ?>
        </form>

        <?php if ($this->search_query !== '') { ?>
            <p class="video-search-info">
                Showing results for: <strong><?php echo htmlspecialchars($this->search_query, ENT_QUOTES, 'UTF-8'); ?></strong>
            </p>
        <?php } ?>

        <?php if ($this->published_videos) { ?>
            <div class="video-grid">
                <?php foreach ($this->published_videos as $video) { ?>
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
                            <?php if (!empty($video->description)) { ?>
                                <p class="video-description">
                                    <?php echo htmlspecialchars($video->description, ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            <?php } ?>
                            <div class="video-meta">
                                by <strong><?php echo htmlspecialchars($video->user_name, ENT_QUOTES, 'UTF-8'); ?></strong>
                                &middot;
                                <?php echo number_format($video->file_size / 1048576, 1); ?> MB
                                &middot;
                                <?php echo htmlspecialchars($video->created_at, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <p>
                <?php if ($this->search_query !== '') { ?>
                    No videos found matching your search.
                <?php } else { ?>
                    No public videos have been shared yet.
                <?php } ?>
            </p>
        <?php } ?>

        <?php if (Session::userIsLoggedIn()) { ?>
            <hr />
            <p><a href="<?php echo Config::get('URL'); ?>video/index" class="btn">Back to My Videos</a></p>
        <?php } ?>

    </div>
</div>
