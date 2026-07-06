<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-key me-2 text-primary"></i>API Keys</h1>
    <p class="vtx-page-desc">
      Bearer tokens for the REST API at <code><?php echo htmlspecialchars($siteUrl); ?>/api/v1/</code>.
      Public GET endpoints work without a key; a key raises your rate limit from 30 to 100 requests/minute.
    </p>
  </div>
  <button type="button" class="btn btn-primary" id="ak-create-btn" data-csrf="{{csrf_token}}">
    <i class="pi pi-plus me-1"></i> New Key
  </button>
</div>

<!-- One-time key reveal -->
<div id="ak-reveal" class="vtx-panel mb-3" style="display:none;">
  <div class="vtx-panel-body">
    <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;">
      <i class="pi pi-key" style="color:var(--ps-warning,#d97706);font-size:1.25rem;"></i>
      <div style="flex:1;min-width:240px;">
        <div style="font-weight:600;font-size:.9rem;margin-bottom:.25rem;">
          Copy this key now - it will not be shown again.
        </div>
        <code id="ak-reveal-key" style="user-select:all;word-break:break-all;font-size:.85rem;"></code>
      </div>
      <button type="button" class="btn btn-outline-secondary btn-sm" id="ak-copy-btn">
        <i class="pi pi-clipboard me-1"></i> Copy
      </button>
    </div>
  </div>
</div>

<div class="vtx-panel">
  <?php if (empty($keys)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-key"></i></div>
    <div class="vtx-empty-title">No API keys</div>
    <div class="vtx-empty-desc">Create a key to authenticate REST API requests with an <code>Authorization: Bearer</code> header.</div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table" data-vtx-table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Status</th>
          <th>Last Used</th>
          <th>Created</th>
          <th style="width:110px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($keys as $key): ?>
        <tr>
          <td><span class="cell-primary"><?php echo htmlspecialchars($key['name']); ?></span></td>
          <td>
            <?php if (!empty($key['revoked_at'])): ?>
            <span class="vtx-tag error">Revoked</span>
            <?php else: ?>
            <span class="vtx-tag success">Active</span>
            <?php endif; ?>
          </td>
          <td class="cell-muted">
            <?php echo !empty($key['last_used_at']) ? date('M j, Y H:i', strtotime($key['last_used_at'])) : 'Never'; ?>
          </td>
          <td class="cell-muted"><?php echo date('M j, Y', strtotime($key['created_at'])); ?></td>
          <td>
            <div style="display:flex;gap:.25rem;justify-content:flex-end;">
              <?php if (empty($key['revoked_at'])): ?>
              <form id="revoke-key-<?php echo $key['id']; ?>" method="POST"
                    action="{{baseUrl}}/admin/api-keys/<?php echo $key['id']; ?>/revoke" style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <button type="button" class="vtx-icon-btn" title="Revoke"
                      data-confirm-form="revoke-key-<?php echo $key['id']; ?>"
                      data-confirm-title="Revoke Key"
                      data-confirm-message="Revoke &quot;<?php echo htmlspecialchars($key['name']); ?>&quot;? Requests using it will fail immediately."
                      data-confirm-label="Revoke"
                      data-confirm-class="btn-danger">
                <i class="pi pi-minus-circle"></i>
              </button>
              <?php endif; ?>
              <form id="del-key-<?php echo $key['id']; ?>" method="POST"
                    action="{{baseUrl}}/admin/api-keys/<?php echo $key['id']; ?>/delete" style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <button type="button" class="vtx-icon-btn danger" title="Delete"
                      data-confirm-form="del-key-<?php echo $key['id']; ?>"
                      data-confirm-title="Delete Key"
                      data-confirm-message="Delete &quot;<?php echo htmlspecialchars($key['name']); ?>&quot; permanently?"
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

<!-- Endpoints reference -->
<div class="vtx-panel mt-3">
  <div class="vtx-panel-header">Endpoints</div>
  <div class="vtx-panel-body" style="font-size:.8125rem;">
    <table style="width:100%;border-collapse:collapse;">
      <?php
      $endpoints = [
          ['GET', '/api/v1/posts',          'Published blog posts (paginated; ?page, ?per_page, ?lang)'],
          ['GET', '/api/v1/posts/{slug}',   'Single post with body'],
          ['GET', '/api/v1/pages',          'Published pages (paginated)'],
          ['GET', '/api/v1/pages/{slug}',   'Single page with content'],
          ['GET', '/api/v1/events',         'Published events (?upcoming=1 for future only)'],
          ['GET', '/api/v1/events/{slug}',  'Single event with tickets and recurrence'],
      ];
      foreach ($endpoints as [$method, $path, $desc]): ?>
      <tr>
        <td style="padding:.35rem .5rem;width:60px;"><span class="vtx-tag success"><?php echo $method; ?></span></td>
        <td style="padding:.35rem .5rem;width:220px;"><code><?php echo $path; ?></code></td>
        <td style="padding:.35rem .5rem;color:var(--ps-text-muted);"><?php echo $desc; ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
