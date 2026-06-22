<div class="container">
    <h1>Upload Video</h1>
    <div class="box">

        <?php $this->renderFeedbackMessages(); ?>

        <form method="post" action="<?php echo Config::get('URL'); ?>video/uploadSave" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />

            <p>
                <label for="video_title">Title:</label><br>
                <input type="text" id="video_title" name="video_title" maxlength="255" required />
            </p>

            <p>
                <label for="video_description">Description:</label><br>
                <textarea id="video_description" name="video_description" rows="4" maxlength="1000"></textarea>
            </p>

            <p>
                <label for="video_file">Select video (MP4, WebM or OGG, max 200 MB):</label><br>
                <input type="file" id="video_file" name="video_file" accept=".mp4,.webm,.ogg" required />
            </p>

            <p>
                <input type="submit" value="Upload" />
            </p>
        </form>

    </div>
</div>