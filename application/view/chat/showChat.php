<div class="container">
    <h1>Chat with <?php echo htmlspecialchars($this->receiver->user_name); ?></h1>
    <div class="box">

        <?php $this->renderFeedbackMessages(); ?>

        <section class="discussion">
            <?php if (!empty($this->messages)) : ?>
                <?php foreach ($this->messages as $message) : ?>
                    <?php $isSender = ($message->sender_user_id == Session::get('user_id')); ?>
                    <div class="bubble <?php echo $isSender ? 'sender' : 'recipient'; ?>">
                        <?php echo htmlspecialchars($message->message_text); ?>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p>No messages yet. Say hello!</p>
            <?php endif; ?>
        </section>

        <form method="post" action="<?php echo Config::get('URL'); ?>chat/send/<?php echo (int)$this->receiver->user_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo Csrf::makeToken(); ?>" />
            <textarea style="width: 95%;" name="message_text" placeholder="Type a message..." required></textarea>
            <input type="submit" value="Send" />
        </form>

    </div>
</div>
