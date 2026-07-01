<?php

declare(strict_types=1);

namespace Config;

use Core\Router;

$router = new Router();

// Enforce maintenance mode before any route is matched
\App\CMS\Maintenance::check();

// Apply locale from ?lang={locale} query string (front-end visitors)
if (isset($_GET['lang'])) {
    \App\CMS\I18n::setLocale($_GET['lang']);
}

/*
 *  default route, $this->add($method,$pattern,$controller,$action)
 *  get|post route, $this->get($pattern,$controller,$action) or $this->post($pattern,$controller,$action)
 */

// ── Setup Wizard ─────────────────────────────────────────────────────────────
$router->get('/setup',           'Setup\WizardController', 'index');
$router->post('/setup/next',     'Setup\WizardController', 'next');
$router->get('/setup/back',      'Setup\WizardController', 'back');
$router->post('/setup/test-db',  'Setup\WizardController', 'testDb');

// ── Admin Auth ────────────────────────────────────────────────────────────────
$router->get('/admin/login',     'Admin\AuthController', 'login');
$router->post('/admin/login',    'Admin\AuthController', 'processLogin');
$router->get('/admin/logout',    'Admin\AuthController', 'logout');
$router->get('/admin/login/2fa', 'Admin\AuthController', 'verify2fa');
$router->post('/admin/login/2fa','Admin\AuthController', 'process2fa');

// ── Admin Redirect ────────────────────────────────────────────────────────────
$router->get('/admin',           'Admin\DashboardController', 'index');

// ── Dashboard ─────────────────────────────────────────────────────────────────
$router->get('/admin/dashboard', 'Admin\DashboardController', 'index');

// ── Users ─────────────────────────────────────────────────────────────────────
$router->get('/admin/users',                        'Admin\UsersController', 'index');
$router->post('/admin/users/store',                 'Admin\UsersController', 'store');
$router->post('/admin/users/([a-zA-Z0-9\-]+)/update',          'Admin\UsersController', 'update');
$router->post('/admin/users/([a-zA-Z0-9\-]+)/delete',          'Admin\UsersController', 'delete');
$router->get('/admin/users/form',                   'Admin\UsersController', 'createForm');
$router->get('/admin/users/([a-zA-Z0-9\-]+)/form',             'Admin\UsersController', 'editForm');

// ── Roles ─────────────────────────────────────────────────────────────────────
$router->get('/admin/roles',                                         'Admin\RolesController', 'index');
$router->post('/admin/roles/store',                                  'Admin\RolesController', 'store');
$router->post('/admin/roles/([a-zA-Z0-9\-]+)/update',               'Admin\RolesController', 'update');
$router->post('/admin/roles/([a-zA-Z0-9\-]+)/delete',               'Admin\RolesController', 'delete');
$router->get('/admin/roles/form',                                    'Admin\RolesController', 'createForm');
$router->get('/admin/roles/([a-zA-Z0-9\-]+)/form',                  'Admin\RolesController', 'editForm');
$router->get('/admin/roles/permissions',                             'Admin\RolesController', 'permissions');
$router->post('/admin/roles/permissions/store',                      'Admin\RolesController', 'storePermission');
$router->post('/admin/roles/permissions/([a-zA-Z0-9\-]+)/delete',   'Admin\RolesController', 'deletePermission');

// ── Modules ───────────────────────────────────────────────────────────────────
$router->get('/admin/modules',                                            'Admin\ModulesController', 'index');
$router->post('/admin/modules/install-bundle',                            'Admin\ModulesController', 'installBundle');
$router->get('/admin/modules/bundles/create',                             'Admin\ModulesController', 'bundleCreate');
$router->post('/admin/modules/bundles/store',                             'Admin\ModulesController', 'bundleStore');
$router->get('/admin/modules/bundles/([a-z0-9\-\_]+)/edit',               'Admin\ModulesController', 'bundleEdit');
$router->post('/admin/modules/bundles/([a-z0-9\-\_]+)/update',            'Admin\ModulesController', 'bundleUpdate');
$router->post('/admin/modules/bundles/([a-z0-9\-\_]+)/delete',            'Admin\ModulesController', 'bundleDelete');
$router->post('/admin/modules/fetch-url',                                  'Admin\ModulesController', 'fetchUrl');
$router->post('/admin/modules/install-from-url',                           'Admin\ModulesController', 'installFromUrl');
$router->post('/admin/modules/([a-z0-9\-\_]+)/toggle',                    'Admin\ModulesController', 'toggle');
$router->post('/admin/modules/([a-z0-9\-\_]+)/install',                   'Admin\ModulesController', 'install');
$router->post('/admin/modules/([a-z0-9\-\_]+)/uninstall',                 'Admin\ModulesController', 'uninstall');
$router->post('/admin/modules/([a-z0-9\-\_]+)/sync-views',                'Admin\ModulesController', 'syncViews');

// ── Themes ────────────────────────────────────────────────────────────────────
$router->get('/admin/themes',                'Admin\ThemesController', 'index');
$router->post('/admin/themes/set-theme',     'Admin\ThemesController', 'setTheme');

// ── Profile ───────────────────────────────────────────────────────────────────
$router->get('/admin/profile',                       'Admin\ProfileController', 'index');
$router->post('/admin/profile/update',               'Admin\ProfileController', 'update');
$router->get('/admin/profile/2fa',                   'Admin\ProfileController', 'twofa');
$router->post('/admin/profile/2fa/setup',            'Admin\ProfileController', 'setup2fa');
$router->post('/admin/profile/2fa/confirm',          'Admin\ProfileController', 'confirm2fa');
$router->get('/admin/profile/2fa/backup-codes',      'Admin\ProfileController', 'backupCodes');
$router->post('/admin/profile/2fa/disable',          'Admin\ProfileController', 'disableTwofa');

// ── Audit Log ─────────────────────────────────────────────────────────────────
$router->get('/admin/audit-log',             'Admin\AuditController', 'index');

// ── Settings ──────────────────────────────────────────────────────────────────
$router->get('/admin/settings',              'Admin\SettingsController', 'index');
$router->post('/admin/settings/save',        'Admin\SettingsController', 'save');
$router->post('/admin/settings/save-mail',   'Admin\SettingsController', 'saveMail');
$router->post('/admin/settings/test-mail',   'Admin\SettingsController', 'testMail');
$router->post('/admin/settings/clear-cache',             'Admin\SettingsController', 'clearCache');
$router->post('/admin/settings/toggle-maintenance',     'Admin\SettingsController', 'toggleMaintenance');
$router->post('/admin/settings/run-migration',          'Admin\SettingsController', 'runMigration');
$router->post('/admin/settings/set-locale',             'Admin\SettingsController', 'setLocale');

// ── Non-core module routes (loaded from DB, only when CMS is installed) ────────
\App\CMS\ModuleManager::loadRoutes($router);

// ── Pages catch-all (must be last so specific module routes like /search win) ──
// Registered here instead of inside Pages/Module.php::registerRoutes() so that
// alphabetical module-load order cannot cause it to shadow later modules.
if (\App\CMS\ModuleLoader::isEnabled('pages')) {
    $router->get('/([a-z0-9][a-z0-9\-]*)', 'App\Modules\Pages\Controllers\Front\PageController', 'show');
}

// ── Front-end placeholder ──────────────────────────────────────────────────────
$router->add('GET', '/', 'Web\Welcome', 'index');

return $router;
