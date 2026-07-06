<?php if (empty($isAjax)): ?>
    <?php if (!isset($isAjax) || !$isAjax): ?>
    <div class="page-header mb-4">
        <a href="<?= $baseUrl ?>/admin/contact" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="pi pi-arrow-left me-1"></i> Back to Inbox
        </a>
        <h1 class="page-title">Contact Settings</h1>
    </div>
<?php endif; ?>
<?php endif; ?>

<div class="card" style="max-width:640px">
    <div class="card-body">
        <form method="POST" action="<?= $baseUrl ?>/admin/contact/settings/save" data-crud-form>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

            <div class="mb-3">
                <label class="form-label">Contact Page Path</label>
                <div class="input-group">
                    <span class="input-group-text"><?= rtrim($baseUrl, '/') ?>/</span>
                    <input type="text" name="contact_path" class="form-control"
                           value="<?= htmlspecialchars($settings['contact_path'] ?? 'contact') ?>"
                           placeholder="contact">
                </div>
                <div class="form-text">URL slug for the public contact form page.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Admin Notification Email</label>
                <input type="email" name="contact_admin_email" class="form-control"
                       value="<?= htmlspecialchars($settings['contact_admin_email'] ?? '') ?>"
                       placeholder="you@example.com">
                <div class="form-text">New submissions will be emailed here. Leave blank to disable.</div>
            </div>

            <hr>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="autoReply" name="contact_auto_reply"
                           value="1" <?= ($settings['contact_auto_reply'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="autoReply">Send auto-reply to visitors</label>
                </div>
            </div>

            <div class="mb-4" id="autoReplyMsg" style="<?= ($settings['contact_auto_reply'] ?? '0') !== '1' ? 'display:none' : '' ?>">
                <label class="form-label">Auto-Reply Message</label>
                <textarea name="contact_auto_reply_msg" class="form-control" rows="4"
                          placeholder="We've received your message and will get back to you as soon as possible."><?= htmlspecialchars($settings['contact_auto_reply_msg'] ?? '') ?></textarea>
                <div class="form-text">Plain text message sent back to the visitor after they submit the form.</div>
            </div>

            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>
