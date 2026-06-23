<?php $this->extend('admin/_layouts/base'); ?>

<?php $this->section('content'); ?>
<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="page-title">Videos</h1>
        <p class="page-subtitle text-muted"><?= $total ?> video<?= $total !== 1 ? 's' : '' ?></p>
    </div>
    <button type="button" class="btn btn-primary" id="btnAddVideo">
        <i class="pi pi-plus me-1"></i> Add Video
    </button>
</div>

<?php if (empty($videos)): ?>
    <div class="card">
        <div class="text-center text-muted py-5">
            <i class="pi pi-video" style="font-size:2.5rem"></i>
            <p class="mt-2">No videos yet. Add your first video!</p>
            <button class="btn btn-primary mt-2" id="btnAddVideoEmpty">Add Video</button>
        </div>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-3">
        <?php foreach ($videos as $v): ?>
            <div class="col" id="video-card-<?= $v['id'] ?>">
                <div class="card h-100">
                    <div class="position-relative" style="padding-top:56.25%;background:#111">
                        <?php if ($v['thumbnail_url']): ?>
                            <img src="<?= htmlspecialchars($v['thumbnail_url']) ?>"
                                 alt="" class="position-absolute top-0 start-0 w-100 h-100" style="object-fit:cover">
                        <?php else: ?>
                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center text-white">
                                <i class="pi pi-video" style="font-size:2rem;opacity:.4"></i>
                            </div>
                        <?php endif; ?>
                        <span class="position-absolute top-0 end-0 m-2 badge bg-<?= $v['status'] === 'published' ? 'success' : 'secondary' ?>">
                            <?= ucfirst($v['status']) ?>
                        </span>
                        <span class="position-absolute bottom-0 start-0 m-2 badge bg-dark text-uppercase" style="font-size:.65rem">
                            <?= htmlspecialchars($v['provider']) ?>
                        </span>
                    </div>
                    <div class="card-body py-2 px-3">
                        <p class="mb-0 fw-semibold text-truncate" title="<?= htmlspecialchars($v['title']) ?>">
                            <?= htmlspecialchars($v['title']) ?>
                        </p>
                    </div>
                    <div class="card-footer bg-transparent py-2 px-3 d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary flex-fill btn-edit"
                                data-id="<?= $v['id'] ?>">Edit</button>
                        <button class="btn btn-sm btn-outline-danger btn-delete"
                                data-id="<?= $v['id'] ?>" title="Delete">
                            <i class="pi pi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
        <div class="d-flex justify-content-center mt-4">
            <nav><ul class="pagination mb-0">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Modal -->
<div class="modal fade" id="videoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="videoModalTitle">Add Video</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="videoModalBody">
                <div class="text-center py-4"><div class="spinner-border"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
const base = <?= json_encode($baseUrl) ?>;

function openModal(title, url) {
    document.getElementById('videoModalTitle').textContent = title;
    const body = document.getElementById('videoModalBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border"></div></div>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('videoModal')).show();
    fetch(url).then(r => r.text()).then(html => { body.innerHTML = html; initForm(body); });
}

function initForm(ctx) {
    const form = ctx.querySelector('form[data-vtx-ajax]');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        fetch(this.action, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) { bootstrap.Modal.getInstance(document.getElementById('videoModal')).hide(); location.reload(); }
                else { const err = ctx.querySelector('.vtx-form-error'); if (err) { err.textContent = d.message; err.style.display = ''; } }
            });
    });
}

document.getElementById('btnAddVideo')?.addEventListener('click', () => openModal('Add Video', `${base}/admin/videos/form`));
document.getElementById('btnAddVideoEmpty')?.addEventListener('click', () => openModal('Add Video', `${base}/admin/videos/form`));

document.addEventListener('click', function (e) {
    const editBtn = e.target.closest('.btn-edit');
    if (editBtn) { openModal('Edit Video', `${base}/admin/videos/${editBtn.dataset.id}/form`); return; }

    const delBtn = e.target.closest('.btn-delete');
    if (delBtn) {
        if (!confirm('Delete this video?')) return;
        const id = delBtn.dataset.id;
        const csrfInput = document.querySelector('meta[name="csrf-token"]');
        const token = csrfInput ? csrfInput.content : '';
        fetch(`${base}/admin/videos/${id}/delete`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'csrf_token=' + encodeURIComponent(token)
        })
        .then(r => r.json())
        .then(d => { if (d.success) { document.getElementById('video-card-' + id)?.closest('.col')?.remove(); } else alert(d.message); });
    }
});
</script>
<?php $this->endSection(); ?>
