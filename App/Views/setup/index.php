<div class="vtx-setup-box">

  <!-- Header + Steps -->
  <div class="vtx-setup-top">
    <div class="vtx-setup-brand">
      <div class="vtx-setup-logo">V</div>
      <div>
        <div style="font-size:.9375rem;font-weight:700;color:var(--ps-text-primary);">Vertext CMS</div>
        <div style="font-size:.6875rem;color:var(--ps-text-muted);">Installation Wizard</div>
      </div>
    </div>

    <!-- Step progress track -->
    <div class="vtx-step-track">
      <?php
      $stepLabels = ['Requirements', 'Database', 'Site Info', 'Admin Account', 'Complete'];
      for ($i = 1; $i <= $totalSteps; $i++):
          $cls = $i < $step ? 'done' : ($i === $step ? 'active' : '');
      ?>
      <div class="vtx-step-item <?php echo $cls; ?>">
        <div class="vtx-step-dot">
          <?php if ($i < $step): ?>✓<?php else: echo $i; endif; ?>
        </div>
      </div>
      <?php endfor; ?>
    </div>
    <div style="display:flex;gap:0;margin-top:.375rem;">
      <?php for ($i = 1; $i <= $totalSteps; $i++):
          $cls = $i < $step ? 'done' : ($i === $step ? 'active' : '');
      ?>
      <div style="flex:1;text-align:center;font-size:.625rem;font-weight:<?php echo $cls==='active'?'700':'500'; ?>;
                  color:<?php echo $cls==='active'?'var(--ps-primary)':($cls==='done'?'#16A34A':'var(--ps-text-muted)'); ?>;">
        <?php echo $stepLabels[$i - 1]; ?>
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- Error alert -->
  <?php if (!empty($error)): ?>
  <div class="vtx-setup-body" style="padding-bottom:0;">
    <div class="vtx-flash error">
      <i class="pi pi-x-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Step Body ────────────────────────────────────────── -->
  <form id="setup-form" method="POST"
        action="{{baseUrl}}/setup/next"
        data-test-url="{{testDbUrl}}">

    <!-- Step 1: Requirements -->
    <?php if ($step === 1): ?>
    <div class="vtx-setup-body">
      <h2 style="font-size:1.125rem;font-weight:700;color:var(--ps-text-primary);margin:0 0 .375rem;">
        <i class="pi pi-check-circle text-primary me-1"></i> System Requirements
      </h2>
      <p style="font-size:.875rem;color:var(--ps-text-muted);margin:0 0 1.25rem;">
        Vertext needs the following to run. All requirements must be met before proceeding.
      </p>

      <div>
        <?php foreach ($reqs as $key => $req): ?>
        <div class="vtx-req">
          <div class="vtx-req-dot <?php echo $req['pass'] ? 'pass' : 'fail'; ?>"></div>
          <div class="vtx-req-label"><?php echo htmlspecialchars($req['label']); ?></div>
          <div class="vtx-req-value"><?php echo htmlspecialchars($req['value']); ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if (!$allPass): ?>
      <div class="vtx-flash error mt-3">
        <i class="pi pi-alert-triangle"></i>
        Please fix the failed requirements before continuing. Refresh the page after making changes.
      </div>
      <?php else: ?>
      <div class="vtx-flash success mt-3">
        <i class="pi pi-check-circle"></i> All requirements are met. You're ready to proceed.
      </div>
      <?php endif; ?>
    </div>

    <div class="vtx-setup-foot">
      <span style="font-size:.8125rem;color:var(--ps-text-muted);">Step 1 of <?php echo $totalSteps; ?></span>
      <button type="submit" class="btn btn-primary" <?php echo !$allPass ? 'disabled' : ''; ?>>
        Next <i class="pi pi-arrow-right ms-1"></i>
      </button>
    </div>

    <!-- Step 2: Database -->
    <?php elseif ($step === 2): ?>
    <div class="vtx-setup-body">
      <h2 style="font-size:1.125rem;font-weight:700;color:var(--ps-text-primary);margin:0 0 .375rem;">
        <i class="pi pi-database text-primary me-1"></i> Database Connection
      </h2>
      <p style="font-size:.875rem;color:var(--ps-text-muted);margin:0 0 1.25rem;">
        Enter your PostgreSQL server credentials. The database will be created automatically if it doesn't exist yet.
      </p>

      <div class="vtx-field">
        <label class="vtx-label" for="db_host">Host <span class="req">*</span></label>
        <input class="form-control" type="text" id="db_host" name="db_host"
               value="<?php echo htmlspecialchars($setupDb['host'] ?? 'localhost'); ?>"
               placeholder="localhost" required>
      </div>

      <div class="row g-3">
        <div class="col-4">
          <div class="vtx-field">
            <label class="vtx-label" for="db_port">Port <span class="req">*</span></label>
            <input class="form-control" type="text" id="db_port" name="db_port"
                   value="<?php echo htmlspecialchars($setupDb['port'] ?? '5432'); ?>"
                   placeholder="5432" required>
          </div>
        </div>
        <div class="col-8">
          <div class="vtx-field">
            <label class="vtx-label" for="db_name">Database Name <span class="req">*</span></label>
            <input class="form-control" type="text" id="db_name" name="db_name"
                   value="<?php echo htmlspecialchars($setupDb['database'] ?? ''); ?>"
                   placeholder="vertext" required>
          </div>
        </div>
      </div>

      <div class="vtx-field">
        <label class="vtx-label" for="db_user">Username <span class="req">*</span></label>
        <input class="form-control" type="text" id="db_user" name="db_user"
               value="<?php echo htmlspecialchars($setupDb['username'] ?? 'postgres'); ?>"
               placeholder="postgres" required>
      </div>

      <div class="vtx-field">
        <label class="vtx-label" for="db_pass">Password</label>
        <input class="form-control" type="password" id="db_pass" name="db_pass" placeholder="Leave empty if no password">
      </div>

      <button type="button" id="test-db-btn" class="btn btn-outline-secondary btn-sm mb-1">
        <i class="pi pi-zap me-1"></i> Test Connection
      </button>
    </div>

    <div class="vtx-setup-foot">
      <a href="{{baseUrl}}/setup/back" class="btn btn-outline-secondary">
        <i class="pi pi-arrow-left me-1"></i> Back
      </a>
      <button type="submit" class="btn btn-primary">
        Next <i class="pi pi-arrow-right ms-1"></i>
      </button>
    </div>

    <!-- Step 3: Site Information -->
    <?php elseif ($step === 3): ?>
    <div class="vtx-setup-body">
      <h2 style="font-size:1.125rem;font-weight:700;color:var(--ps-text-primary);margin:0 0 .375rem;">
        <i class="pi pi-globe text-primary me-1"></i> Site Information
      </h2>
      <p style="font-size:.875rem;color:var(--ps-text-muted);margin:0 0 1.25rem;">
        Configure your site name, URL, and locale settings.
      </p>

      <div class="vtx-field">
        <label class="vtx-label" for="site_name">Site Name <span class="req">*</span></label>
        <input class="form-control" type="text" id="site_name" name="site_name"
               value="<?php echo htmlspecialchars($setupApp['siteName'] ?? 'My Website'); ?>"
               placeholder="My Website" required>
      </div>

      <div class="vtx-field">
        <label class="vtx-label" for="site_url">Site URL <span class="req">*</span></label>
        <input class="form-control" type="url" id="site_url" name="site_url"
               value="<?php echo htmlspecialchars($setupApp['siteUrl'] ?? ''); ?>"
               placeholder="https://example.com" required>
        <div class="vtx-help">Full URL including protocol (https://)</div>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="vtx-field">
            <label class="vtx-label" for="language">Language</label>
            <select class="form-select" id="language" name="language">
              <option value="en" <?php echo ($setupApp['lang'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
              <option value="id" <?php echo ($setupApp['lang'] ?? '') === 'id' ? 'selected' : ''; ?>>Bahasa Indonesia</option>
            </select>
          </div>
        </div>
        <div class="col-md-6">
          <div class="vtx-field">
            <label class="vtx-label" for="timezone">Timezone</label>
            <select class="form-select" id="timezone" name="timezone">
              <?php foreach ($timezones as $tz): ?>
              <option value="<?php echo htmlspecialchars($tz); ?>"
                      <?php echo ($setupApp['timezone'] ?? 'UTC') === $tz ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($tz); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="vtx-setup-foot">
      <a href="{{baseUrl}}/setup/back" class="btn btn-outline-secondary">
        <i class="pi pi-arrow-left me-1"></i> Back
      </a>
      <button type="submit" class="btn btn-primary">
        Next <i class="pi pi-arrow-right ms-1"></i>
      </button>
    </div>

    <!-- Step 4: Admin Account -->
    <?php elseif ($step === 4): ?>
    <div class="vtx-setup-body">
      <h2 style="font-size:1.125rem;font-weight:700;color:var(--ps-text-primary);margin:0 0 .375rem;">
        <i class="pi pi-user text-primary me-1"></i> Administrator Account
      </h2>
      <p style="font-size:.875rem;color:var(--ps-text-muted);margin:0 0 1.25rem;">
        Create the first admin account. This will have full access to the CMS.
      </p>

      <div class="vtx-field">
        <label class="vtx-label" for="admin_name">Full Name <span class="req">*</span></label>
        <input class="form-control" type="text" id="admin_name" name="admin_name"
               placeholder="Administrator" required>
      </div>

      <div class="vtx-field">
        <label class="vtx-label" for="admin_email">Email Address <span class="req">*</span></label>
        <input class="form-control" type="email" id="admin_email" name="admin_email"
               placeholder="admin@example.com" required>
      </div>

      <div class="vtx-field">
        <label class="vtx-label" for="admin_password">Password <span class="req">*</span></label>
        <div style="position:relative;">
          <input class="form-control" type="password" id="admin_password" name="admin_password"
                 placeholder="At least 8 characters" minlength="8" required>
          <button type="button" class="vtx-icon-btn"
                  data-pw-toggle="admin_password"
                  style="position:absolute;right:6px;top:50%;transform:translateY(-50%);border:none;">
            <i class="pi pi-eye-off"></i>
          </button>
        </div>
      </div>

      <div class="vtx-field">
        <label class="vtx-label" for="admin_password_confirm">Confirm Password <span class="req">*</span></label>
        <input class="form-control" type="password" id="admin_password_confirm"
               name="admin_password_confirm" placeholder="Repeat password" required>
      </div>
    </div>

    <div class="vtx-setup-foot">
      <a href="{{baseUrl}}/setup/back" class="btn btn-outline-secondary">
        <i class="pi pi-arrow-left me-1"></i> Back
      </a>
      <button type="submit" class="btn btn-success">
        <i class="pi pi-check me-1"></i> Complete Installation
      </button>
    </div>

    <!-- Step 5: Complete -->
    <?php elseif ($step === 5): ?>
    <div class="vtx-setup-body" style="text-align:center;padding:2.5rem 2rem;">
      <div style="width:64px;height:64px;background:rgba(22,163,74,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-size:1.75rem;color:#16A34A;">
        <i class="pi pi-check-circle"></i>
      </div>
      <h2 style="font-size:1.375rem;font-weight:700;color:var(--ps-text-primary);margin:0 0 .625rem;">
        Installation Complete!
      </h2>
      <p style="font-size:.9375rem;color:var(--ps-text-secondary);margin:0 0 2rem;max-width:360px;margin-left:auto;margin-right:auto;">
        Vertext CMS has been successfully installed. You can now log in to the admin panel.
      </p>

      <div style="background:var(--ps-bg-surface);border:1px solid var(--ps-border);border-radius:8px;padding:1rem;text-align:left;margin-bottom:1.5rem;font-size:.8125rem;">
        <div style="display:flex;justify-content:space-between;padding:.25rem 0;">
          <span style="color:var(--ps-text-muted);">Admin URL</span>
          <code>{{baseUrl}}/admin</code>
        </div>
      </div>

      <a href="{{baseUrl}}/admin/login" class="btn btn-primary btn-lg">
        <i class="pi pi-arrow-right me-1"></i> Go to Login
      </a>
    </div>
    <?php endif; ?>

  </form>

</div>
