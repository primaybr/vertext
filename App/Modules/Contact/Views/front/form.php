<style>
.contact-page { padding: 3rem 0; }
.contact-form { background: #fff; border: 1px solid var(--clr-border, #e5e7eb); border-radius: 8px; padding: 2rem; }
.contact-form label { display: block; font-size: .875rem; font-weight: 600; margin-bottom: .35rem; }
.contact-form input,
.contact-form textarea { width: 100%; padding: .6rem .85rem; border: 1px solid var(--clr-border, #e5e7eb); border-radius: 6px; font-size: 1rem; font-family: inherit; }
.contact-form input:focus,
.contact-form textarea:focus { outline: none; border-color: var(--clr-accent, #4f46e5); box-shadow: 0 0 0 3px rgba(79,70,229,.15); }
.contact-form .form-group { margin-bottom: 1.25rem; }
.contact-form .btn-submit { background: var(--clr-accent, #4f46e5); color: #fff; border: none; border-radius: 6px; padding: .65rem 1.5rem; font-size: 1rem; font-weight: 600; cursor: pointer; }
.contact-form .btn-submit:hover { opacity: .9; }
.alert { padding: .9rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: .95rem; }
.alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
</style>

<div class="container contact-page">
    <div style="max-width:640px; margin:0 auto">
        <h1 style="margin-bottom:1.5rem">Contact Us</h1>

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
                    <label for="cf-name">Name <span style="color:red">*</span></label>
                    <input type="text" id="cf-name" name="name" required maxlength="120"
                           placeholder="Your name">
                </div>

                <div class="form-group">
                    <label for="cf-email">Email <span style="color:red">*</span></label>
                    <input type="email" id="cf-email" name="email" required maxlength="180"
                           placeholder="your@email.com">
                </div>

                <div class="form-group">
                    <label for="cf-subject">Subject</label>
                    <input type="text" id="cf-subject" name="subject" maxlength="200"
                           placeholder="What's this about?">
                </div>

                <div class="form-group">
                    <label for="cf-message">Message <span style="color:red">*</span></label>
                    <textarea id="cf-message" name="message" required rows="6" maxlength="3000"
                              placeholder="Your message…"></textarea>
                </div>

                <button type="submit" class="btn-submit">Send Message</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
