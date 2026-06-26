<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title"><i class="pi pi-link me-2 text-primary"></i>Webhooks</h1>
    <p class="vtx-page-desc">Outgoing webhooks with HMAC-SHA256 signed payloads.</p>
  </div>
  <?php if (\App\CMS\Auth::can('webhooks.manage')): ?>
  <div>
    <a href="{{baseUrl}}/admin/webhooks/create" class="btn btn-primary">
      <i class="pi pi-plus me-1"></i> New Endpoint
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- Endpoints list -->
<div class="vtx-panel">
  <?php if (empty($endpoints)): ?>
  <div class="vtx-empty">
    <div class="vtx-empty-ico"><i class="pi pi-link"></i></div>
    <div class="vtx-empty-title">No webhook endpoints</div>
    <div class="vtx-empty-desc">Add an endpoint to start receiving event payloads.</div>
  </div>
  <?php else: ?>
  <div class="vtx-table-wrap">
    <table class="vtx-table">
      <thead>
        <tr>
          <th>Name / URL</th>
          <th>Events</th>
          <th>Status</th>
          <th>Last Delivery</th>
          <th style="width:140px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($endpoints as $ep): ?>
        <tr>
          <td>
            <div style="font-weight:500;font-size:.875rem;"><?php echo htmlspecialchars($ep['name']); ?></div>
            <div style="font-size:.75rem;color:var(--ps-text-muted);word-break:break-all;"><?php echo htmlspecialchars($ep['url']); ?></div>
          </td>
          <td>
            <?php if (empty($ep['events_arr'])): ?>
            <span style="font-size:.75rem;color:var(--ps-text-muted);">none</span>
            <?php else: ?>
            <div style="display:flex;flex-wrap:wrap;gap:.25rem;">
              <?php foreach ($ep['events_arr'] as $evt): ?>
              <span style="font-size:.6875rem;background:var(--ps-bg-subtle);border:1px solid var(--ps-border);border-radius:4px;padding:.1rem .375rem;color:var(--ps-text-secondary);">
                <?php echo htmlspecialchars($availableEvents[$evt] ?? $evt); ?>
              </span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($ep['enabled']): ?>
            <span class="vtx-badge" style="background:var(--ps-success,#16a34a);color:#fff;">Active</span>
            <?php else: ?>
            <span class="vtx-badge" style="background:var(--ps-text-muted);color:#fff;">Paused</span>
            <?php endif; ?>
          </td>
          <td style="font-size:.8125rem;">
            <?php if ($ep['last_delivery']): ?>
            <div style="color:<?php echo $ep['last_delivery_success'] ? 'var(--ps-success,#16a34a)' : 'var(--ps-danger,#dc2626)'; ?>;">
              <?php echo $ep['last_delivery_success'] ? '&#10003;' : '&#10007;'; ?>
              <?php echo htmlspecialchars(substr($ep['last_delivery'], 0, 16)); ?>
            </div>
            <?php else: ?>
            <span style="color:var(--ps-text-muted);">Never</span>
            <?php endif; ?>
          </td>
          <td style="text-align:right;">
            <div style="display:flex;gap:.375rem;justify-content:flex-end;align-items:center;">
              <?php if (\App\CMS\Auth::can('webhooks.manage')): ?>
              <button type="button" class="vtx-icon-btn"
                      id="test-btn-<?php echo $ep['id']; ?>"
                      title="Send test ping"
                      onclick="vtxTestWebhook('<?php echo $ep['id']; ?>')">
                <i class="pi pi-refresh"></i>
              </button>
              <a href="{{baseUrl}}/admin/webhooks/<?php echo $ep['id']; ?>/edit"
                 class="vtx-icon-btn" title="Edit">
                <i class="pi pi-edit"></i>
              </a>
              <form id="del-wh-<?php echo $ep['id']; ?>" method="POST"
                    action="{{baseUrl}}/admin/webhooks/<?php echo $ep['id']; ?>/delete" style="display:none;">
                <input type="hidden" name="csrf_token" value="{{csrf_token}}">
              </form>
              <button type="button" class="vtx-icon-btn danger" title="Delete"
                      data-confirm-form="del-wh-<?php echo $ep['id']; ?>"
                      data-confirm-title="Delete Endpoint"
                      data-confirm-message="Delete &quot;<?php echo htmlspecialchars($ep['name']); ?>&quot;? All delivery logs will also be removed."
                      data-confirm-label="Delete"
                      data-confirm-class="btn-danger"
                      data-confirm-ajax="true">
                <i class="pi pi-trash"></i>
              </button>
              <?php endif; ?>
              <a href="{{baseUrl}}/admin/webhooks/<?php echo $ep['id']; ?>/logs"
                 class="vtx-icon-btn" title="View logs">
                <i class="pi pi-list"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Signing info panel -->
<div class="vtx-panel mt-3">
  <div class="vtx-panel-head">
    <h2 class="vtx-panel-title">Payload Signing</h2>
  </div>
  <div class="vtx-panel-body" style="font-size:.8125rem;line-height:1.6;color:var(--ps-text-secondary);">
    <p>Every delivery includes these headers:</p>
    <ul style="margin:.5rem 0 0 1rem;padding:0;">
      <li><code>X-Vertext-Signature: sha256={HMAC}</code> - HMAC-SHA256 of the raw JSON body signed with your secret</li>
      <li><code>X-Vertext-Event: {event}</code> - the event name (e.g. <code>post.published</code>)</li>
      <li><code>X-Vertext-Delivery: {id}</code> - unique delivery identifier</li>
    </ul>
    <p style="margin-top:.75rem;">Verify in PHP: <code>hash_equals('sha256=' . hash_hmac('sha256', $rawBody, $secret), $_SERVER['HTTP_X_VERTEXT_SIGNATURE'])</code></p>
  </div>
</div>

<script>
function vtxTestWebhook(id) {
    var btn = document.getElementById('test-btn-' + id);
    if (btn) { btn.disabled = true; btn.querySelector('i').className = 'pi pi-spin pi-refresh'; }
    var fd = new FormData();
    fd.append('csrf_token', '{{csrf_token}}');
    VtxAjax.postForm('{{baseUrl}}/admin/webhooks/' + id + '/test', fd, function (res) {
        if (btn) { btn.disabled = false; btn.querySelector('i').className = 'pi pi-refresh'; }
        Phuse.toast(res.message, res.success ? 'success' : 'error');
        if (res.success) setTimeout(function() { window.location.reload(); }, 1200);
    });
}
</script>
