<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title">
      <a href="{{baseUrl}}/admin/profile" class="vtx-breadcrumb-link">My Profile</a>
      <span class="vtx-breadcrumb-sep">/</span>
      Backup Codes
    </h1>
    <p class="vtx-page-desc">Save these codes in a safe place - they are shown only once.</p>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7 col-xl-6">

    <div class="vtx-panel mb-4" style="border-color:var(--ps-warning,#d97706);border-width:2px;">
      <div class="vtx-panel-head" style="background:var(--ps-warning-bg,#fef3c7);border-bottom-color:var(--ps-warning,#d97706);">
        <h2 class="vtx-panel-title" style="display:flex;align-items:center;gap:.5rem;">
          <i class="pi pi-warning" style="color:var(--ps-warning,#d97706);"></i>
          Save your backup codes
        </h2>
        <p class="vtx-panel-desc">
          These codes will not be shown again. Each code can only be used once.
        </p>
      </div>
      <div class="vtx-panel-body">

        <div id="codes-grid"
             style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:1.5rem;">
          <?php foreach ($backup_codes as $code): ?>
          <code style="display:block;padding:.5rem .75rem;background:var(--ps-bg);border:1px solid var(--ps-border);border-radius:6px;font-size:.95rem;letter-spacing:.1em;text-align:center;font-family:monospace;">
            <?php echo htmlspecialchars($code); ?>
          </code>
          <?php endforeach; ?>
        </div>

        <div class="d-flex gap-2 flex-wrap">
          <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyAllCodes()">
            <i class="pi pi-copy me-1"></i> Copy all
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printCodes()">
            <i class="pi pi-print me-1"></i> Print
          </button>
        </div>

      </div>
    </div>

    <div class="vtx-panel mb-4">
      <div class="vtx-panel-body" style="font-size:.875rem;color:var(--ps-text-muted);">
        <ul style="margin:0;padding-left:1.25rem;line-height:1.8;">
          <li>Store these codes in a password manager or secure document.</li>
          <li>Each code works once only - it is crossed off after use.</li>
          <li>If you run low, disable and re-enable 2FA to generate a new set.</li>
          <li>Without these codes and your authenticator, you will be locked out.</li>
        </ul>
      </div>
    </div>

    <a href="{{baseUrl}}/admin/profile" class="btn btn-primary">
      <i class="pi pi-check me-1"></i> I have saved my backup codes
    </a>

  </div>
</div>

<script>
function copyAllCodes() {
  const codes = <?php echo json_encode($backup_codes); ?>;
  navigator.clipboard.writeText(codes.join('\n')).then(() => {
    Phuse.toast('Backup codes copied to clipboard.', 'success');
  });
}
function printCodes() {
  const codes = <?php echo json_encode($backup_codes); ?>;
  const w = window.open('', '_blank', 'width=400,height=500');
  w.document.write(
    '<html><head><title>Vertext CMS - Backup Codes</title>' +
    '<style>body{font-family:monospace;padding:2rem;}h2{margin-bottom:1rem;}' +
    'code{display:block;padding:.3rem;border:1px solid #ccc;border-radius:4px;' +
    'margin:.25rem 0;font-size:1rem;letter-spacing:.08em;}</style></head><body>' +
    '<h2>Vertext CMS - 2FA Backup Codes</h2>' +
    '<p>Each code can only be used once.</p>' +
    codes.map(c => '<code>' + c + '</code>').join('') +
    '</body></html>'
  );
  w.document.close();
  w.print();
}
</script>
