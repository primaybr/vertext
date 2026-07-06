<?php
$postId = $post['id'] ?? '';
?>
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title">
      <a href="<?php echo $baseUrl; ?>/admin/blog/posts" class="text-muted" style="font-weight:400;font-size:1rem;">Posts</a>
      <span class="text-muted mx-1">/</span>
      <i class="pi pi-history me-2 text-primary"></i>Revisions
    </h1>
    <p class="vtx-page-desc">History for: <strong><?php echo htmlspecialchars($post['title'] ?? ''); ?></strong></p>
  </div>
  <button type="button" class="btn btn-outline-secondary btn-sm"
          data-form-url="<?php echo $baseUrl; ?>/admin/blog/posts/<?php echo htmlspecialchars($postId); ?>/form"
          data-form-title="Edit Post"
          data-form-size="modal-xl">
    <i class="pi pi-edit me-1"></i> Edit Post
  </button>
</div>

<div class="vtx-panel">
  <?php if (empty($revisions)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-history"></i></div>
    <div class="vtx-empty-title">No revisions yet</div>
    <div class="vtx-empty-desc">Revisions are saved automatically each time you update the post.</div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table">
      <thead>
        <tr>
          <th style="width:80px;">#</th>
          <th>Title at save time</th>
          <th style="width:100px;">Status</th>
          <th style="width:160px;">Saved</th>
          <th style="width:140px;">By</th>
          <th style="width:160px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($revisions as $rev): ?>
        <tr>
          <td style="color:var(--ps-text-muted);font-size:.8125rem;">Rev <?php echo (int) $rev['revision_number']; ?></td>
          <td class="cell-primary"><?php echo htmlspecialchars($rev['title'] ?? '(no title)'); ?></td>
          <td>
            <?php $sc = match($rev['status'] ?? '') { 'published' => 'success', 'draft' => 'warning', 'scheduled' => 'primary', default => 'gray' }; ?>
            <span class="vtx-tag <?php echo $sc; ?>"><?php echo ucfirst(htmlspecialchars($rev['status'] ?? '-')); ?></span>
          </td>
          <td style="font-size:.8125rem;color:var(--ps-text-muted);">
            <?php echo !empty($rev['created_at']) ? date('M d, Y H:i', strtotime($rev['created_at'])) : '-'; ?>
          </td>
          <td style="font-size:.8125rem;color:var(--ps-text-muted);">
            <?php echo htmlspecialchars($rev['created_by_name'] ?? '-'); ?>
          </td>
          <td>
            <div style="display:flex;gap:.375rem;flex-wrap:wrap;">
            <button type="button" class="btn btn-outline-secondary btn-sm"
                    data-form-url="<?php echo $baseUrl; ?>/admin/blog/posts/<?php echo htmlspecialchars($postId); ?>/revisions/<?php echo htmlspecialchars($rev['id']); ?>/diff"
                    data-form-title="Revision #<?php echo (int) $rev['revision_number']; ?> - Compare"
                    data-form-size="modal-xl">
              <i class="pi pi-eye me-1"></i>Compare
            </button>
            <?php if (\App\CMS\Auth::can('posts.edit')): ?>
            <form id="restore-rev-<?php echo $rev['id']; ?>" method="POST"
                  action="<?php echo $baseUrl; ?>/admin/blog/posts/<?php echo htmlspecialchars($postId); ?>/revisions/<?php echo htmlspecialchars($rev['id']); ?>/restore"
                  style="display:none;">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
            </form>
            <button type="button" class="btn btn-outline-secondary btn-sm"
                    data-confirm-form="restore-rev-<?php echo $rev['id']; ?>"
                    data-confirm-title="Restore Revision"
                    data-confirm-message="Restore revision #<?php echo (int) $rev['revision_number']; ?>? The current version will be saved as a new revision first."
                    data-confirm-label="Restore"
                    data-confirm-class="btn-primary"
                    data-confirm-ajax="true">
              <i class="pi pi-history me-1"></i>Restore
            </button>
            <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="vtx-panel-foot" style="padding:.75rem 1rem;">
    <span style="font-size:.8125rem;color:var(--ps-text-muted);">
      <?php echo count($revisions); ?> revision<?php echo count($revisions) !== 1 ? 's' : ''; ?> stored.
      Revisions capture title, content, status, excerpt, and SEO fields.
    </span>
  </div>
  <?php endif; ?>
</div>
