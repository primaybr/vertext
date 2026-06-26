<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">Contact Inbox</h1>
        <p class="page-subtitle text-muted"><?= $total ?> submission<?= $total !== 1 ? 's' : '' ?></p>
    </div>
    <a href="<?= $baseUrl ?>/admin/contact/settings" class="btn btn-outline-secondary">
        <i class="pi pi-cog me-1"></i> Settings
    </a>
</div>

<!-- Filter tabs -->
<ul class="nav nav-tabs mb-3">
    <?php foreach (['all' => 'All', 'unread' => 'Unread', 'read' => 'Read', 'spam' => 'Spam'] as $k => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filter === $k ? 'active' : '' ?>" href="<?= $baseUrl ?>/admin/contact?status=<?= $k ?>">
                <?= $label ?>
                <?php if ($k === 'unread' && $unreadCnt > 0): ?>
                    <span class="badge bg-danger ms-1"><?= $unreadCnt ?></span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($items)): ?>
            <div class="text-center text-muted py-5">
                <i class="pi pi-inbox" style="font-size:2rem"></i>
                <p class="mt-2">No submissions found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $row): ?>
                            <tr class="<?= $row['status'] === 'unread' ? 'fw-semibold' : '' ?>">
                                <td>
                                    <?php $statusColors = ['unread' => 'danger', 'read' => 'secondary', 'spam' => 'warning', 'replied' => 'success']; ?>
                                    <span class="badge bg-<?= $statusColors[$row['status']] ?? 'secondary' ?>">
                                        <?= ucfirst($row['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><?= htmlspecialchars($row['subject'] ?? '') ?></td>
                                <td><?= $row['submitted_at'] ? date('M j, Y g:i A', strtotime($row['submitted_at'])) : '-' ?></td>
                                <td>
                                    <a href="<?= $baseUrl ?>/admin/contact/<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-1"
                                            data-action="delete" data-id="<?= $row['id'] ?>">
                                        <i class="pi pi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pages > 1): ?>
                <div class="d-flex justify-content-center py-3">
                    <nav>
                        <ul class="pagination mb-0">
                            <?php for ($i = 1; $i <= $pages; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?status=<?= $filter ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('[data-action="delete"]').forEach(btn => {
    btn.addEventListener('click', function () {
        if (!confirm('Delete this submission?')) return;
        const id = this.dataset.id;
        fetch(`<?= $baseUrl ?>/admin/contact/${id}/delete`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'csrf_token=<?= htmlspecialchars($csrfToken ?? '') ?>'
        })
        .then(r => r.json())
        .then(d => { if (d.success) this.closest('tr').remove(); else Phuse.toast(d.message, 'error'); })
        .catch(() => Phuse.toast('Request failed.', 'error'));
    });
});
</script>
