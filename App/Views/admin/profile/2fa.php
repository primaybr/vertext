<!-- Page Header -->
<div class="vtx-page-head">
  <div>
    <h1 class="vtx-page-title">
      <a href="{{baseUrl}}/admin/profile" class="vtx-breadcrumb-link">My Profile</a>
      <span class="vtx-breadcrumb-sep">/</span>
      Two-Factor Authentication
    </h1>
    <p class="vtx-page-desc">Add a second layer of security to your account.</p>
  </div>
</div>

<?php
$enabled      = $twofa_enabled ?? false;
$setupPending = $setup_pending ?? false;
$secret       = $setup_secret  ?? '';
$rawSecret    = $setup_raw     ?? '';
$uri          = $setup_uri     ?? '';
?>

<?php if (!empty($flash['message'])): ?>
<div class="vtx-flash <?php echo htmlspecialchars($flash['type'] ?? 'info'); ?> mb-4">
  <?php echo htmlspecialchars($flash['message']); ?>
</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-7 col-xl-6">

    <?php if ($enabled && !$setupPending): ?>
    <!-- ── 2FA is ON ─────────────────────────────────────────────────────── -->
    <div class="vtx-panel mb-4">
      <div class="vtx-panel-head" style="display:flex;align-items:center;gap:.75rem;">
        <span style="width:32px;height:32px;border-radius:50%;background:var(--ps-success-bg,#d1fae5);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="pi pi-shield" style="color:var(--ps-success,#059669);font-size:.95rem;"></i>
        </span>
        <div>
          <h2 class="vtx-panel-title" style="margin:0;">Two-Factor Authentication</h2>
          <span class="vtx-tag success" style="font-size:.7rem;">Active</span>
        </div>
      </div>
      <div class="vtx-panel-body">
        <p style="color:var(--ps-text-muted);font-size:.9rem;margin-bottom:1.25rem;">
          Your account is protected. You will be asked for a 6-digit code every time you sign in.
        </p>

        <details style="margin-bottom:0;">
          <summary style="cursor:pointer;font-size:.875rem;font-weight:500;color:var(--ps-danger,#dc2626);list-style:none;display:flex;align-items:center;gap:.4rem;">
            <i class="pi pi-trash" style="font-size:.8rem;"></i> Disable 2FA
          </summary>
          <div style="margin-top:1rem;padding:1rem;background:var(--ps-bg-subtle,var(--ps-bg));border-radius:6px;border:1px solid var(--ps-border);">
            <p style="font-size:.875rem;color:var(--ps-text-muted);margin-bottom:.75rem;">
              Enter your current password and a valid authentication code (or backup code) to confirm.
            </p>
            <form method="POST" action="{{baseUrl}}/admin/profile/2fa/disable">
              <input type="hidden" name="csrf_token" value="{{csrf_token}}">

              <div class="vtx-field mb-3">
                <label class="vtx-label" for="dis-password">Current Password</label>
                <input class="form-control" type="password" id="dis-password" name="password"
                       autocomplete="current-password" required>
              </div>

              <div class="vtx-field mb-3">
                <label class="vtx-label" for="dis-code">Authentication Code</label>
                <input class="form-control" type="text" id="dis-code" name="code"
                       inputmode="numeric" maxlength="11"
                       placeholder="6-digit code or backup code"
                       autocomplete="one-time-code">
              </div>

              <button type="submit" class="btn btn-danger">Disable 2FA</button>
            </form>
          </div>
        </details>
      </div>
    </div>

    <?php elseif ($setupPending): ?>
    <!-- ── Setup in progress ─────────────────────────────────────────────── -->
    <div class="vtx-panel mb-4">
      <div class="vtx-panel-head">
        <h2 class="vtx-panel-title">Step 1 - Add to your authenticator app</h2>
      </div>
      <div class="vtx-panel-body">
        <p style="color:var(--ps-text-muted);font-size:.875rem;margin-bottom:1rem;">
          Open Google Authenticator, Authy, Bitwarden, 1Password, or any TOTP app.
          Choose <strong>Add account manually</strong> and enter the key below.
        </p>

        <div style="margin-bottom:1.25rem;">
          <label class="vtx-label">Secret Key (manual entry)</label>
          <div style="display:flex;align-items:center;gap:.5rem;">
            <code id="secret-display"
                  data-secret="<?php echo htmlspecialchars($rawSecret ?? '', ENT_QUOTES); ?>"
                  style="flex:1;padding:.5rem .75rem;background:var(--ps-bg);border:1px solid var(--ps-border);border-radius:6px;font-size:1rem;letter-spacing:.12em;word-break:break-all;">
              <?php echo htmlspecialchars($secret); ?>
            </code>
            <button type="button" onclick="copySecret()"
                    class="btn btn-outline-secondary btn-sm" id="copy-btn" style="flex-shrink:0;">
              <i class="pi pi-copy"></i>
            </button>
          </div>
          <div class="vtx-field-hint">Issuer: Vertext CMS - Algorithm: SHA1 - Digits: 6 - Period: 30s</div>
        </div>

        <?php if ($uri): ?>
        <div style="margin-bottom:1.25rem;">
          <label class="vtx-label">Open in authenticator app</label>
          <a href="<?php echo htmlspecialchars($uri); ?>"
             style="display:inline-flex;align-items:center;gap:.4rem;font-size:.875rem;color:var(--ps-primary);">
            <i class="pi pi-arrow-right"></i> Open otpauth:// link
          </a>
          <div class="vtx-field-hint">On mobile, this link opens your authenticator app directly.</div>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <div class="vtx-panel mb-4">
      <div class="vtx-panel-head">
        <h2 class="vtx-panel-title">Step 2 - Verify your code</h2>
        <p class="vtx-panel-desc">Enter the 6-digit code your app shows to confirm setup.</p>
      </div>
      <div class="vtx-panel-body">
        <form method="POST" action="{{baseUrl}}/admin/profile/2fa/confirm">
          <input type="hidden" name="csrf_token" value="{{csrf_token}}">

          <div class="vtx-field mb-3">
            <label class="vtx-label" for="code">6-Digit Code</label>
            <input class="form-control" type="text" id="code" name="code"
                   inputmode="numeric" pattern="\d{6}" maxlength="6"
                   placeholder="000000" autocomplete="one-time-code" autofocus
                   style="font-size:1.15rem;letter-spacing:.18em;max-width:180px;">
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">Enable 2FA</button>
            <a href="{{baseUrl}}/admin/profile" class="btn btn-outline-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>

    <?php else: ?>
    <!-- ── 2FA is OFF ─────────────────────────────────────────────────────── -->
    <div class="vtx-panel mb-4">
      <div class="vtx-panel-head" style="display:flex;align-items:center;gap:.75rem;">
        <span style="width:32px;height:32px;border-radius:50%;background:var(--ps-warning-bg,#fef3c7);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="pi pi-shield" style="color:var(--ps-warning,#d97706);font-size:.95rem;"></i>
        </span>
        <div>
          <h2 class="vtx-panel-title" style="margin:0;">Two-Factor Authentication</h2>
          <span class="vtx-tag warning" style="font-size:.7rem;">Not enabled</span>
        </div>
      </div>
      <div class="vtx-panel-body">
        <p style="color:var(--ps-text-muted);font-size:.9rem;margin-bottom:1.25rem;">
          Protect your account with a time-based one-time password (TOTP).
          Works with Google Authenticator, Authy, Bitwarden, 1Password, and any RFC 6238-compatible app.
        </p>
        <form method="POST" action="{{baseUrl}}/admin/profile/2fa/setup">
          <input type="hidden" name="csrf_token" value="{{csrf_token}}">
          <button type="submit" class="btn btn-primary">
            <i class="pi pi-shield me-1"></i> Enable Two-Factor Authentication
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- Sidebar info -->
  <div class="col-lg-4 offset-lg-1">
    <div class="vtx-panel">
      <div class="vtx-panel-head">
        <h3 class="vtx-panel-title" style="font-size:.875rem;">How it works</h3>
      </div>
      <div class="vtx-panel-body" style="font-size:.875rem;color:var(--ps-text-muted);line-height:1.65;">
        <p style="margin-bottom:.75rem;">
          After entering your password, you will be asked for a 6-digit code that
          rotates every 30 seconds in your authenticator app.
        </p>
        <p style="margin-bottom:.75rem;">
          Even if your password is compromised, an attacker cannot log in without
          physical access to your device.
        </p>
        <p style="margin-bottom:0;">
          <strong style="color:var(--ps-text-primary);">Backup codes:</strong> You will
          receive 8 single-use backup codes when you enable 2FA. Store them securely -
          they are your only recovery option if you lose your device.
        </p>
      </div>
    </div>
  </div>

</div>
