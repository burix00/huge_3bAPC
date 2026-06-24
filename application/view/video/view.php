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

        <!-- Like / Dislike -->
        <div class="rating-bar" data-video-id="<?php echo (int) $this->video->video_id; ?>">
            <button type="button" class="rating-btn rating-like <?php echo ($this->user_vote === 1) ? 'active' : ''; ?>"
                    data-action="like">
                &#128077; <span class="like-count"><?php echo (int) $this->likes; ?></span>
            </button>
            <button type="button" class="rating-btn rating-dislike <?php echo ($this->user_vote === 0) ? 'active' : ''; ?>"
                    data-action="dislike">
                &#128078; <span class="dislike-count"><?php echo (int) $this->dislikes; ?></span>
            </button>
        </div>

        <hr />

        <!-- Comments -->
        <h2>Comments (<?php echo count($this->comments); ?>)</h2>

        <form method="post"
              action="<?php echo Config::get('URL'); ?>video/addComment/<?php echo (int) $this->video->video_id; ?>"
              class="comment-form">
            <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />
            <textarea name="comment_text" rows="3" maxlength="1000"
                      placeholder="Write a comment&hellip;" required></textarea>
            <input type="submit" value="Post Comment" class="btn" />
        </form>

        <div class="comment-list">
            <?php if ($this->comments) { ?>
                <?php foreach ($this->comments as $comment) { ?>
                    <div class="comment">
                        <div class="comment-head">
                            <strong><?php echo htmlspecialchars($comment->user_name, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span class="comment-date"><?php echo htmlspecialchars($comment->created_at, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="comment-body">
                            <?php echo nl2br(htmlspecialchars($comment->comment_text, ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                        <?php if ($comment->user_id == Session::get('user_id')) { ?>
                            <form method="post"
                                  action="<?php echo Config::get('URL'); ?>video/deleteComment/<?php echo (int) $comment->comment_id; ?>/<?php echo (int) $this->video->video_id; ?>"
                                  style="display:inline;"
                                  onsubmit="return confirm('Delete this comment?');">
                                <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />
                                <input type="submit" value="Delete" class="btn btn-small btn-danger" />
                            </form>
                        <?php } ?>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <p>No comments yet. Be the first!</p>
            <?php } ?>
        </div>

        <hr />
        <p>
            <a href="<?php echo Config::get('URL'); ?>video/index">My Videos</a>
            &nbsp;&middot;&nbsp;
            <a href="<?php echo Config::get('URL'); ?>video/publicVideos">Public Videos</a>
        </p>

    </div>
</div>

<script>
(function ($) {
    'use strict';

    var rateUrl   = '<?php echo Config::get('URL'); ?>video/rate/';
    var csrfToken = '<?php echo Csrf::makeToken(); ?>';

    $('.rating-bar').on('click', '.rating-btn', function () {
        var $bar    = $(this).closest('.rating-bar');
        var videoId = $bar.data('video-id');
        var action  = $(this).data('action');

        $.ajax({
            url:      rateUrl + videoId,
            type:     'POST',
            dataType: 'json',
            data:     { action: action, csrf_token: csrfToken },
            success: function (data) {
                if (data.status === 'ok') {
                    $bar.find('.like-count').text(data.likes);
                    $bar.find('.dislike-count').text(data.dislikes);
                    $bar.find('.rating-like').toggleClass('active', data.userVote === 1);
                    $bar.find('.rating-dislike').toggleClass('active', data.userVote === 0);
                }
            }
        });
    });

}(jQuery));
</script>
