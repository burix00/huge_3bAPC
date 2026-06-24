<div class="container">
    <h1>Upload Video</h1>
    <div class="box">

        <?php $this->renderFeedbackMessages(); ?>

        <form id="videoUploadForm" class="upload-form">
            <input type="hidden" id="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />

            <p>
                <label for="video_title">Title:</label><br>
                <input type="text" id="video_title" name="video_title" maxlength="255" required />
            </p>

            <p>
                <label for="video_description">Description:</label><br>
                <textarea id="video_description" name="video_description" rows="4" maxlength="1000"></textarea>
            </p>

            <p>
                <label for="video_file">Select video (MP4, WebM or OGG, max 10 GB):</label><br>
                <input type="file" id="video_file" name="video_file" accept=".mp4,.webm,.ogg,video/*" required />
            </p>

            <p>
                <label for="video_thumbnail">Thumbnail image (JPEG, PNG, GIF or WebP, max 5 MB &mdash; optional):</label><br>
                <input type="file" id="video_thumbnail" name="video_thumbnail" accept="image/*" />
            </p>

            <div id="upload-progress-wrap" class="upload-progress-wrap" style="display:none;">
                <div id="upload-progress-bar" class="upload-progress-bar"></div>
                <span id="upload-progress-pct" class="upload-progress-pct">0%</span>
            </div>

            <p id="upload-status" class="upload-status"></p>

            <p>
                <input type="submit" id="upload-submit" value="Upload" />
            </p>
        </form>
        <hr />
        <div class="video-nav-buttons">
            <a href="<?php echo Config::get('URL'); ?>video/index" class="btn">My Videos</a>
            <a href="<?php echo Config::get('URL'); ?>video/publicVideos" class="btn">Public Videos</a>
        </div>

    </div>
</div>

<script>
(function ($) {
    'use strict';

    var CHUNK_SIZE   = 5 * 1024 * 1024;   // 5 MB per chunk
    var MAX_RETRIES  = 3;
    var uploadUrl    = '<?php echo Config::get('URL'); ?>video/uploadChunk';
    var redirectUrl  = '<?php echo Config::get('URL'); ?>video/index';

    $('#videoUploadForm').on('submit', function (e) {
        e.preventDefault();

        var file  = document.getElementById('video_file').files[0];
        var title = $.trim($('#video_title').val());

        if (!file) {
            setStatus('Please select a video file.', 'error');
            return;
        }
        if (!title) {
            setStatus('Please enter a title.', 'error');
            return;
        }

        var totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        var uploadUuid  = generateUuid();

        $('#upload-submit').prop('disabled', true);
        $('#upload-progress-wrap').show();
        setStatus('');
        updateBar(0, totalChunks);

        sendChunk(0, totalChunks, file, uploadUuid, 0);
    });

    function sendChunk(index, totalChunks, file, uuid, retries) {
        var start = index * CHUNK_SIZE;
        var end   = Math.min(start + CHUNK_SIZE, file.size);
        var slice = file.slice(start, end);

        var fd = new FormData();
        fd.append('file_chunk',         slice);
        fd.append('chunkIndex',         index);
        fd.append('totalChunks',        totalChunks);
        fd.append('totalSize',          file.size);
        fd.append('uploadUuid',         uuid);
        fd.append('originalName',       file.name);
        fd.append('video_title',        $('#video_title').val());
        fd.append('video_description',  $('#video_description').val());
        fd.append('csrf_token',         $('#csrf_token').val());

        // Send thumbnail only with the first chunk
        if (index === 0) {
            var thumbFile = document.getElementById('video_thumbnail').files[0];
            if (thumbFile) {
                fd.append('video_thumbnail', thumbFile);
            }
        }

        $.ajax({
            url:         uploadUrl,
            type:        'POST',
            data:        fd,
            processData: false,
            contentType: false,
            dataType:    'json',
            success: function (data) {
                if (data.status === 'ok') {
                    updateBar(index + 1, totalChunks);
                    sendChunk(index + 1, totalChunks, file, uuid, 0);
                } else if (data.status === 'done') {
                    updateBar(totalChunks, totalChunks);
                    setStatus('Upload complete! Redirecting&hellip;', 'ok');
                    setTimeout(function () {
                        window.location.href = redirectUrl;
                    }, 1000);
                } else {
                    var msg = (data.message) ? data.message : 'Upload failed.';
                    setStatus(msg, 'error');
                    $('#upload-submit').prop('disabled', false);
                }
            },
            error: function () {
                if (retries < MAX_RETRIES) {
                    setStatus('Chunk ' + (index + 1) + ' failed, retrying (' + (retries + 1) + '/' + MAX_RETRIES + ')&hellip;', 'warn');
                    setTimeout(function () {
                        sendChunk(index, totalChunks, file, uuid, retries + 1);
                    }, 1500);
                } else {
                    setStatus('Upload failed after ' + MAX_RETRIES + ' retries. Please try again.', 'error');
                    $('#upload-submit').prop('disabled', false);
                }
            }
        });
    }

    function updateBar(done, total) {
        var pct = Math.round((done / total) * 100);
        $('#upload-progress-bar').css('width', pct + '%');
        $('#upload-progress-pct').text(pct + '%');
    }

    function setStatus(msg, type) {
        var el = $('#upload-status');
        el.html(msg);
        el.removeClass('upload-status-ok upload-status-error upload-status-warn');
        if (type) { el.addClass('upload-status-' + type); }
    }

    function generateUuid() {
        if (window.crypto && window.crypto.randomUUID) {
            return window.crypto.randomUUID();
        }
        // Fallback for older browsers
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

}(jQuery));
</script>