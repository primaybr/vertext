<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-message me-2 text-primary"></i>Comments</h1>
    <p class="vtx-page-desc">Review and moderate reader comments.</p>
  </div>
</div>

<!-- Status filter tabs -->
<div class="vtx-panel mb-3">
  <div class="vtx-filter-tabs">
    <?php
    $tabs = [
      'pending'  => ['label' => 'Pending',  'icon' => 'pi-clock'],
      'approved' => ['label' => 'Approved', 'icon' => 'pi-check-circle'],
      'spam'     => ['label' => 'Spam',     'icon' => 'pi-x-circle'],
      'all'      => ['label' => 'All',      'icon' => 'pi-list'],
    ];
    foreach ($tabs as $key => $tab): ?>
    <a href="{{baseUrl}}/admin/blog/comments?status=<?php echo $key; ?>"
       class="vtx-filter-tab <?php echo ($status ?? 'pending') === $key ? 'active' : ''; ?>">
      <i class="pi <?php echo $tab['icon']; ?>"></i>
      <?php echo $tab['label']; ?>
      <?php if (isset($counts[$key])): ?>
      <span class="count"><?php echo $counts[$key]; ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="vtx-panel">
  <?php if (empty($comments)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-message"></i></div>
    <div class="vtx-empty-title">No <?php echo ($status ?? 'pending') !== 'all' ? htmlspecialchars($status ?? 'pending') . ' ' : ''; ?>comments</div>
    <div class="vtx-empty-desc">Nothing to moderate here.</div>
  </div>
  <?php else: ?>
  <div class="vtx-panel-body" style="padding:0;">
    <?php foreach ($comments as $c):
          $statusClass = match($c['status'] ?? '') {
              'approved' => 'success',
              'spam'     => 'error',
              default    => 'amber',
          };
    ?>
    <div style="padding:.875rem 1rem;border-bottom:1px solid var(--ps-border);">
      <div style="display:flex;align-items:flex-start;gap:.75rem;justify-content:space-between;flex-wrap:wrap;">
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.375rem;">
            <span style="font-size:.8125rem;font-weight:600;color:var(--ps-text-primary);">
              <?php echo htmlspecialchars($c['author_name']); ?>
            </span>
            <?php if (!empty($c['author_email'])): ?>
            <span style="font-size:.75rem;color:var(--ps-text-muted);"><?php echo htmlspecialchars($c['author_email']); ?></span>
            <?php endif; ?>
            <span class="vtx-tag <?php echo $statusClass; ?>" style="font-size:.6875rem;">
              <?php echo ucfirst($c['status']); ?>
            </span>
            <span style="font-size:.75rem;color:var(--ps-text-muted);">
              on <em><?php echo htmlspecialchars($c['post_title'] ?? '—'); ?></em>
            </span>
          </div>
          <p style="font-size:.875rem;color:var(--ps-text-secondary);margin:0;line-height:1.5;">
            <?php echo nl2br(htmlspecialchars($c['body'])); ?>
          </p>
          <div style="font-size:.75rem;color:var(--ps-text-muted);margin-top:.375rem;">
            <?php echo date('M d, Y H:i', strtotime($c['created_at'])); ?>
            <?php if (!empty($c['ip_address'])): ?> · <?php echo htmlspecialchars($c['ip_address']); ?><?php endif; ?>
          </div>
        </div>
        <div style="display:flex;gap:.375rem;flex-shrink:0;align-items:center;">
          <?php if ($c['status'] !== 'approved' && \App\CMS\Auth::can('comments.moderate')): ?>
          <form method="POST" action="{{baseUrl}}/admin/blog/comments/<?php echo $c['id']; ?>/approve" style="display:inline;">
            <input type="hidden" name="csrf_token" value="{{csrf_token}}">
            <button type="submit" class="btn btn-outline-secondary btn-sm" title="Approve"
                    data-ajax-form data-toast="Comment approved.">
              <i class="pi pi-check-circle"></i>
            </button>
          </form>
          <?php endif; ?>
          <?php if ($c['status'] !== 'spam' && \App\CMS\Auth::can('comments.moderate')): ?>
          <form method="POST" action="{{baseUrl}}/admin/blog/comments/<?php echo $c['id']; ?>/spam" style="display:inline;">
            <input type="hidden" name="csrf_token" value="{{csrf_token}}">
            <button type="submit" class="btn btn-outline-secondary btn-sm" title="Spam"
                    data-ajax-form data-toast="Marked as spam.">
              <i class="pi pi-x-circle"></i>
            </button>
          </form>
          <?php endif; ?>
          <?php if (\App\CMS\Auth::can('comments.delete')): ?>
          <form id="del-cmt-<?php echo $c['id']; ?>" method="POST"
                action="{{baseUrl}}/admin/blog/comments/<?php echo $c['id']; ?>/delete" style="display:none;">
            <input type="hidden" name="csrf_token" value="{{csrf_token}}">
          </form>
          <button type="button" class="vtx-icon-btn danger" title="Delete"
                  data-confirm-form="del-cmt-<?php echo $c['id']; ?>"
                  data-confirm-title="Delete Comment"
                  data-confirm-message="Permanently delete this comment?"
                  data-confirm-label="Delete"
                  data-confirm-class="btn-danger"
                  data-confirm-ajax="true">
            <i class="pi pi-trash"></i>
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
