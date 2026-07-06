<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= $baseUrl ?>/admin/contact" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="pi pi-arrow-left me-1"></i> Back to Inbox
        </a>
        <h1 class="page-title"><?= htmlspecialchars($item['name']) ?></h1>
    </div>
    <div class="d-flex gap-2" id="contactItemActions"
         data-id="<?= htmlspecialchars((string) $item['id']) ?>"
         data-csrf="<?= htmlspecialchars($csrfToken ?? '') ?>"
         data-base-url="<?= htmlspecialchars($baseUrl) ?>">
        <?php if ($item['status'] !== 'spam'): ?>
            <button type="button" class="btn btn-outline-warning" id="btnSpam">Mark Spam</button>
        <?php endif; ?>
        <?php if ($item['status'] !== 'read'): ?>
            <button type="button" class="btn btn-outline-secondary" id="btnRead">Mark Read</button>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-danger" id="btnDelete">Delete</button>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <strong><?= htmlspecialchars($item['subject'] ?? '(no subject)') ?></strong>
            </div>
            <div class="card-body" style="white-space:pre-wrap"><?= htmlspecialchars($item['message']) ?></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Submission Details</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Name</dt>
                    <dd class="col-sm-7"><?= htmlspecialchars($item['name']) ?></dd>

                    <dt class="col-sm-5">Email</dt>
                    <dd class="col-sm-7">
                        <a href="mailto:<?= htmlspecialchars($item['email']) ?>">
                            <?= htmlspecialchars($item['email']) ?>
                        </a>
                    </dd>

                    <dt class="col-sm-5">Status</dt>
                    <dd class="col-sm-7">
                        <?php $statusColors = ['unread' => 'danger', 'read' => 'secondary', 'spam' => 'warning', 'replied' => 'success']; ?>
                        <span class="badge bg-<?= $statusColors[$item['status']] ?? 'secondary' ?>">
                            <?= ucfirst($item['status']) ?>
                        </span>
                    </dd>

                    <dt class="col-sm-5">Submitted</dt>
                    <dd class="col-sm-7">
                        <?= $item['submitted_at'] ? date('M j, Y \a\t g:i A', strtotime($item['submitted_at'])) : '-' ?>
                    </dd>

                    <?php if ($item['ip_address']): ?>
                    <dt class="col-sm-5">IP Address</dt>
                    <dd class="col-sm-7"><?= htmlspecialchars($item['ip_address']) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">Quick Reply</div>
            <div class="card-body">
                <a href="mailto:<?= htmlspecialchars($item['email']) ?>?subject=Re: <?= urlencode($item['subject'] ?? '') ?>"
                   class="btn btn-primary w-100">
                    <i class="pi pi-envelope me-1"></i> Reply via Email
                </a>
            </div>
        </div>
    </div>
</div>
