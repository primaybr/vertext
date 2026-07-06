<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{pageTitle}} - Vertext CMS</title>
  <link rel="icon" type="image/svg+xml" href="{{assetsUrl}}images/logo/favicon.svg">
  <link rel="stylesheet" href="{{assetsUrl}}css/styles.css?v=<?php echo substr(hash('crc32b', \App\CMS\Version::APP), 0, 8); ?>">
  <link rel="stylesheet" href="{{assetsUrl}}css/admin.css?v=<?php echo substr(hash('crc32b', \App\CMS\Version::APP), 0, 8); ?>">
  <link rel="stylesheet" href="{{assetsUrl}}css/admin-pages.css?v=<?php echo substr(hash('crc32b', \App\CMS\Version::APP), 0, 8); ?>">
  <?php foreach (\App\CMS\ModuleLoader::assets()['css'] as $__mAsset): ?>
  <link rel="stylesheet" href="<?php echo htmlspecialchars(($assetsUrl ?? '') . $__mAsset); ?>">
  <?php endforeach; ?>
  <?php include ROOT . 'App' . DS . 'Views' . DS . '_shared' . DS . 'theme-init.php'; ?>
  <script>window.VTX_ASSETS_URL = '{{assetsUrl}}'; window.VTX_BASE_URL = '{{baseUrl}}';</script>
  <script src="{{assetsUrl}}js/admin.js?v=<?php echo substr(hash('crc32b', \App\CMS\Version::APP), 0, 8); ?>"></script>
</head>
<body>

<div id="vtx-overlay" class="vtx-overlay"></div>

<div class="vtx-wrapper">

  <!-- ── Sidebar ─────────────────────────────────────────── -->
  <aside id="vtx-sidebar" class="vtx-sidebar">

    <a href="{{baseUrl}}/admin/dashboard" class="vtx-sidebar-brand text-decoration-none">
      <div class="vtx-sidebar-logo"><img src="{{assetsUrl}}images/logo/logo-dark.svg" alt="" style="width:60%;height:auto;"></div>
      <div>
        <div class="vtx-sidebar-name">Vertext</div>
        <div class="vtx-sidebar-ver">CMS v<?php echo \App\CMS\Version::APP; ?></div>
      </div>
    </a>

    <nav class="vtx-sidebar-nav">
      <div class="vtx-nav-section">Main</div>

      <a href="{{baseUrl}}/admin/dashboard"
         class="vtx-nav-link {% if activeMenu == 'dashboard' %}active{% endif %}">
        <i class="pi pi-home"></i> Dashboard
      </a>

      <div class="vtx-nav-section">Management</div>

      <a href="{{baseUrl}}/admin/users"
         class="vtx-nav-link {% if activeMenu == 'users' %}active{% endif %}">
        <i class="pi pi-users"></i> Users
      </a>

      <a href="{{baseUrl}}/admin/roles"
         class="vtx-nav-link {% if activeMenu == 'roles' %}active{% endif %}">
        <i class="pi pi-shield"></i> Roles &amp; Permissions
      </a>

      <div class="vtx-nav-section">System</div>

      <a href="{{baseUrl}}/admin/modules"
         class="vtx-nav-link {% if activeMenu == 'modules' %}active{% endif %}">
        <i class="pi pi-layers"></i> Module Manager
      </a>

      <a href="{{baseUrl}}/admin/themes"
         class="vtx-nav-link {% if activeMenu == 'themes' %}active{% endif %}">
        <i class="pi pi-sliders"></i> Themes
      </a>

      <a href="{{baseUrl}}/admin/settings"
         class="vtx-nav-link {% if activeMenu == 'settings' %}active{% endif %}">
        <i class="pi pi-settings"></i> Settings
      </a>

      <a href="{{baseUrl}}/admin/audit-log"
         class="vtx-nav-link {% if activeMenu == 'audit-log' %}active{% endif %}">
        <i class="pi pi-clock"></i> Audit Log
      </a>

      <?php if (\App\CMS\Auth::can('api.manage')): ?>
      <a href="{{baseUrl}}/admin/api-keys"
         class="vtx-nav-link {% if activeMenu == 'api-keys' %}active{% endif %}">
        <i class="pi pi-key"></i> API Keys
      </a>
      <?php endif; ?>

      <?php if (\App\CMS\Auth::can('translations.manage')): ?>
      <a href="{{baseUrl}}/admin/translations"
         class="vtx-nav-link {% if activeMenu == 'translations' %}active{% endif %}">
        <i class="pi pi-languages"></i> Translations
      </a>
      <?php endif; ?>

      <?php $__moduleNav = \App\CMS\ModuleLoader::navItems(); ?>
      <?php if (!empty($__moduleNav)): ?>
      <div class="vtx-nav-section">Modules</div>
      <?php foreach ($__moduleNav as $__navItem):
            $__isGroupActive = str_starts_with(($activeMenu ?? ''), $__navItem['active']);
            $__hasSubnav     = !empty($__navItem['subnav']);
      ?>
        <?php if ($__hasSubnav): ?>
        <div class="vtx-nav-group <?php echo $__isGroupActive ? 'open' : ''; ?>">
          <button type="button"
                  class="vtx-nav-group-btn <?php echo $__isGroupActive ? 'active' : ''; ?>"
                  aria-expanded="<?php echo $__isGroupActive ? 'true' : 'false'; ?>">
            <i class="pi <?php echo htmlspecialchars($__navItem['icon']); ?>"></i>
            <span><?php echo htmlspecialchars($__navItem['label']); ?></span>
            <i class="pi pi-chevron-down vtx-nav-chevron"></i>
          </button>
          <div class="vtx-subnav">
            <?php
                  $__navBasePath = rtrim($__navItem['path'], '/');
                  foreach ($__navItem['subnav'] as $__sub):
                      $__suffix         = ltrim(substr($__sub['path'], strlen($__navBasePath)), '/');
                      $__subKey         = $__navItem['active'] . '.' . ($__suffix ?: 'dashboard');
                      $__subActiveClass = (($activeMenu ?? '') === $__subKey) ? 'active' : '';
            ?>
            <a href="<?php echo htmlspecialchars($baseUrl ?? ''); ?><?php echo htmlspecialchars($__sub['path']); ?>"
               class="vtx-subnav-link <?php echo $__subActiveClass; ?>">
              <i class="pi <?php echo htmlspecialchars($__sub['icon']); ?>"></i>
              <?php echo htmlspecialchars($__sub['label']); ?>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php else: ?>
        <a href="<?php echo htmlspecialchars($baseUrl ?? ''); ?><?php echo htmlspecialchars($__navItem['path']); ?>"
           class="vtx-nav-link <?php echo $__isGroupActive ? 'active' : ''; ?>">
          <i class="pi <?php echo htmlspecialchars($__navItem['icon']); ?>"></i>
          <?php echo htmlspecialchars($__navItem['label']); ?>
        </a>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php endif; ?>

      <div class="vtx-nav-divider"></div>

      <a href="{{baseUrl}}/" class="vtx-nav-link" target="_blank" rel="noopener">
        <i class="pi pi-globe"></i> View Site
      </a>
    </nav>

    <div class="vtx-sidebar-foot">
      <div class="vtx-sidebar-user">
        <?php if (!empty($avatarUrl)): ?>
        <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt=""
             style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;">
        <?php else: ?>
        <div class="vtx-avatar">{{currentUser.name|substr:0:1|upper}}</div>
        <?php endif; ?>
        <div style="flex:1;min-width:0;">
          <div class="vtx-avatar-name">{{currentUser.name}}</div>
          <div class="vtx-avatar-role">Administrator</div>
        </div>
        <a href="#"
           class="vtx-icon-btn"
           title="Logout"
           onclick="event.preventDefault();vtxConfirmModal({title:'Log out',message:'You will be returned to the login page.',confirmLabel:'Log out',confirmClass:'btn-danger',onConfirm:function(){window.location.href=window.VTX_BASE_URL+'/admin/logout';}})">
          <i class="pi pi-arrow-right"></i>
        </a>
      </div>
    </div>

  </aside>
  <!-- /sidebar -->

  <!-- ── Main ──────────────────────────────────────────────── -->
  <div class="vtx-main">

    <!-- Top bar -->
    <header class="vtx-topbar">
      <button id="sidebar-toggle" class="vtx-topbar-toggle" type="button" aria-label="Toggle sidebar">
        <i class="pi pi-menu"></i>
      </button>

      <div class="vtx-topbar-left">
        <ol class="vtx-breadcrumb">
          <li class="vtx-breadcrumb-item">
            <a href="{{baseUrl}}/admin/dashboard">Home</a>
          </li>
          <li class="vtx-breadcrumb-item"><span class="sep">/</span></li>
          <li class="vtx-breadcrumb-item current">{{pageTitle}}</li>
        </ol>
      </div>

      <div class="vtx-topbar-actions">
        <!-- Theme toggle -->
        <button id="theme-toggle" class="vtx-icon-btn" type="button" title="Toggle theme">
          <i id="theme-icon" class="pi pi-moon"></i>
        </button>

        <!-- Locale switcher -->
        <?php
        $__curLocale  = \App\CMS\I18n::getLocale();
        $__allLocales = \App\CMS\I18n::getSupportedLocales();
        if (count($__allLocales) > 1):
        ?>
        <form id="vtx-locale-form" method="POST" action="{{baseUrl}}/admin/settings/set-locale" style="display:flex;align-items:center;margin:0;">
          <input type="hidden" name="csrf_token" value="{{csrf_token}}">
          <select name="locale" class="vtx-locale-select" onchange="document.getElementById('vtx-locale-form').submit()" title="Switch language" aria-label="Language">
            <?php foreach ($__allLocales as $__loc): ?>
            <option value="<?= htmlspecialchars($__loc) ?>"<?= $__loc === $__curLocale ? ' selected' : '' ?>><?= strtoupper(htmlspecialchars($__loc)) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php endif; ?>

        <!-- User menu -->
        <div class="vtx-user-menu">
          <button id="user-menu-trigger" class="vtx-user-trigger" type="button">
            <?php if (!empty($avatarUrl)): ?>
            <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt=""
                 style="width:24px;height:24px;border-radius:50%;object-fit:cover;">
            <?php else: ?>
            <div class="vtx-avatar" style="width:24px;height:24px;font-size:.6875rem;">
              {{currentUser.name|substr:0:1|upper}}
            </div>
            <?php endif; ?>
            <span>{{currentUser.name}}</span>
            <i class="pi pi-chevron-down" style="font-size:.75rem;opacity:.6;"></i>
          </button>
          <div id="user-menu" class="vtx-dropdown-menu">
            <a href="{{baseUrl}}/admin/profile" class="vtx-dropdown-item">
              <i class="pi pi-user"></i> My Profile
            </a>
            <a href="{{baseUrl}}/admin/settings" class="vtx-dropdown-item">
              <i class="pi pi-settings"></i> Settings
            </a>
            <div class="vtx-dropdown-divider"></div>
            <a href="#" class="vtx-dropdown-item danger"
               onclick="event.preventDefault();vtxConfirmModal({title:'Log out',message:'You will be returned to the login page.',confirmLabel:'Log out',confirmClass:'btn-danger',onConfirm:function(){window.location.href=window.VTX_BASE_URL+'/admin/logout';}})">
              <i class="pi pi-arrow-right"></i> Logout
            </a>
          </div>
        </div>
      </div>
    </header>

    <!-- Main content -->
    <main class="vtx-content">
      {!! content !!}
    </main>

    <footer style="padding:.75rem 1.5rem;border-top:1px solid var(--ps-border);font-size:.75rem;color:var(--ps-text-muted);">
      Vertext CMS &copy; <?php echo date('Y'); ?> - Built on <a href="https://github.com/primaybr/phuse" target="_blank" rel="noopener" style="color:var(--ps-primary);text-decoration:none;">Phuse Framework</a>
    </footer>

  </div>
  <!-- /main -->

</div>

<script src="{{assetsUrl}}js/scripts.js?v=<?php echo substr(hash('crc32b', \App\CMS\Version::APP), 0, 8); ?>"></script>
<script src="{{assetsUrl}}js/admin-pages.js?v=<?php echo substr(hash('crc32b', \App\CMS\Version::APP), 0, 8); ?>"></script>
<?php foreach (\App\CMS\ModuleLoader::assets()['js'] as $__mAsset): ?>
<script src="<?php echo htmlspecialchars(($assetsUrl ?? '') . $__mAsset); ?>"></script>
<?php endforeach; ?>

<!-- CRUD form modal -->
<div id="vtx-form-modal" class="modal fade" role="dialog" aria-modal="true" aria-labelledby="vtx-form-modal-title">
  <div class="modal-dialog modal-lg" id="vtx-form-modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="vtx-form-modal-title"></h5>
        <button type="button" class="btn-close" id="vtx-form-modal-close" aria-label="Close"><i class="pi pi-x"></i></button>
      </div>
      <div class="modal-body" id="vtx-form-modal-body" style="padding:1.25rem;">
        <div style="text-align:center;padding:2rem;color:var(--ps-text-muted);">Loading…</div>
      </div>
    </div>
  </div>
</div>

<!-- Shared confirm modal -->
<div id="vtx-confirm-modal" class="modal fade" role="dialog" aria-modal="true" aria-labelledby="vtx-confirm-title">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="vtx-confirm-title"></h5>
        <button type="button" class="btn-close" id="vtx-modal-close" aria-label="Close"><i class="pi pi-x"></i></button>
      </div>
      <div class="modal-body">
        <p id="vtx-confirm-message" style="margin:0;font-size:.9375rem;color:var(--ps-text-secondary);"></p>
        <input type="text" id="vtx-confirm-input" class="form-control form-control-sm" style="display:none;margin-top:.875rem;">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="vtx-modal-cancel">Cancel</button>
        <button type="button" class="btn btn-sm" id="vtx-modal-confirm">Confirm</button>
      </div>
    </div>
  </div>
</div>
<div id="vtx-modal-backdrop" class="modal-backdrop fade" style="display:none;"></div>

<?php if (!empty($flash['message'])): ?>
<div id="vtx-flash-data" data-message="<?php echo htmlspecialchars($flash['message'], ENT_QUOTES); ?>" data-type="<?php echo htmlspecialchars($flash['type'] ?? 'info', ENT_QUOTES); ?>" hidden></div>
<?php endif; ?>
</body>
</html>
