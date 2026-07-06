<div class="container contact-page">
        <h1 class="contact-page-header">Contact Us</h1>

        <div class="contact-form-wrap">
        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($flash) || $flash['type'] !== 'success'): ?>
        <div class="contact-form">
            <form method="POST" action="<?= $baseUrl ?>/contact">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

                <div class="form-group">
                    <label for="cf-name">Name <span class="req">*</span></label>
                    <input type="text" id="cf-name" name="name" required maxlength="120"
                           placeholder="Your name">
                </div>

                <div class="form-group">
                    <label for="cf-email">Email <span class="req">*</span></label>
                    <input type="email" id="cf-email" name="email" required maxlength="180"
                           placeholder="your@email.com">
                </div>

                <div class="form-group">
                    <label for="cf-subject">Subject</label>
                    <input type="text" id="cf-subject" name="subject" maxlength="200"
                           placeholder="What's this about?">
                </div>

                <div class="form-group">
                    <label for="cf-message">Message <span class="req">*</span></label>
                    <textarea id="cf-message" name="message" required rows="6" maxlength="3000"
                              placeholder="Your message&hellip;"></textarea>
                </div>

                <button type="submit" class="btn-submit">Send Message</button>
            </form>
        </div>
        <?php endif; ?>
        </div>
</div>
