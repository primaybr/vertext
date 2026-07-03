<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-users me-2 text-primary"></i>Audience Segments</h1>
    <p class="vtx-page-desc">Saved subscriber filters that campaigns can target instead of the full list.</p>
  </div>
  <button type="button" class="btn btn-primary"
          data-form-url="{{baseUrl}}/admin/newsletter/segments/form"
          data-form-title="New Segment">
    <i class="pi pi-plus me-1"></i> New Segment
  </button>
</div>

<div class="vtx-panel">
  <?php if (empty($segments)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-users"></i></div>
    <div class="vtx-empty-title">No segments yet</div>
    <div class="vtx-empty-desc">
      Create a segment to send campaigns to a slice of your audience -
      for example only subscribers who joined through the blog widget.
    </div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table" data-vtx-table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Rules</th>
          <th>Matching Subscribers</th>
          <th style="width:80px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($segments as $row): ?>
        <tr>
          <td><span class="cell-primary"><?php echo htmlspecialchars($row['name']); ?></span></td>
          <td class="cell-muted" style="font-size:.8125rem;">
            <?php
            $parts = [];
            $r = $row['rules_decoded'];
            if (!empty($r['source']))            $parts[] = 'source = ' . htmlspecialchars($r['source']);
            if (!empty($r['subscribed_after']))  $parts[] = 'joined after ' . htmlspecialchars($r['subscribed_after']);
            if (!empty($r['subscribed_before'])) $parts[] = 'joined before ' . htmlspecialchars($r['subscribed_before']);
            echo $parts ? implode(' &middot; ', $parts) : '<em>All active subscribers</em>';
            ?>
          </td>
          <td><span class="vtx-tag"><?php echo (int) $row['match_count']; ?></span></td>
          <td>
            <div style="display:flex;gap:.25rem;justify-content:flex-end;">
              <button type="button" class="vtx-icon-btn" title="Edit"
                      data-form-url="{{baseUrl}}/admin/newsletter/segments/<?php echo $row['id']; ?>/form"
                      data-form-title="Edit Segment">
                <i class="pi pi-edit"></i>
              </button>
              <form id="del-seg-<?php echo $row['id']; ?>" method="POST"
                    action="{{baseUrl}}/admin/newsletter/segments/<?php echo $row['id']; ?>/delete"
                    style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <button type="button" class="vtx-icon-btn danger" title="Delete"
                      data-confirm-form="del-seg-<?php echo $row['id']; ?>"
                      data-confirm-title="Delete Segment"
                      data-confirm-message="Delete &quot;<?php echo htmlspecialchars($row['name']); ?>&quot;? Campaigns using it will fall back to all active subscribers."
                      data-confirm-label="Delete"
                      data-confirm-class="btn-danger"
                      data-confirm-ajax="true">
                <i class="pi pi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
