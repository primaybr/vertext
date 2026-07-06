<div class="vtx-page-head" id="vtx-videos-index">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-video me-2 text-primary"></i>Videos</h1>
    <p class="vtx-page-desc"><?= $total ?> video<?= $total !== 1 ? 's' : '' ?></p>
  </div>
  <?php if (\App\CMS\Auth::can('videos.create')): ?>
  <button type="button" class="btn btn-primary"
          data-form-url="{{baseUrl}}/admin/videos/form"
          data-form-title="Add Video">
    <i class="pi pi-plus me-1"></i> Add Video
  </button>
  <?php endif; ?>
</div>

<?php if (empty($videos)): ?>
<div class="vtx-panel">
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-video"></i></div>
    <div class="vtx-empty-title">No videos yet</div>
    <div class="vtx-empty-desc">Add your first video to get started.</div>
    <?php if (\App\CMS\Auth::can('videos.create')): ?>
    <button type="button" class="btn btn-primary mt-3"
            data-form-url="{{baseUrl}}/admin/videos/form"
            data-form-title="Add Video">
      <i class="pi pi-plus me-1"></i> Add Video
    </button>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-3">
  <?php foreach ($videos as $v): ?>
  <div class="col">
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
        <?php if (\App\CMS\Auth::can('videos.edit')): ?>
        <button type="button" class="btn btn-sm btn-outline-primary flex-fill"
                data-form-url="{{baseUrl}}/admin/videos/<?= $v['id'] ?>/form"
                data-form-title="Edit Video">Edit</button>
        <?php endif; ?>
        <?php if (\App\CMS\Auth::can('videos.delete')): ?>
        <form id="del-video-<?= $v['id'] ?>" method="POST"
              action="{{baseUrl}}/admin/videos/<?= $v['id'] ?>/delete" style="display:none;">
          <input type="hidden" name="csrf_token" value="{{csrf_token}}">
        </form>
        <button type="button" class="btn btn-sm btn-outline-danger"
                data-confirm-form="del-video-<?= $v['id'] ?>"
                data-confirm-title="Delete Video"
                data-confirm-message="Delete &quot;<?= htmlspecialchars($v['title']) ?>&quot;? This cannot be undone."
                data-confirm-label="Delete"
                data-confirm-class="btn-danger"
                data-confirm-ajax="true"
                title="Delete">
          <i class="pi pi-trash"></i>
        </button>
        <?php endif; ?>
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

