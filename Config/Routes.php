<?php

declare(strict_types=1);

namespace Config;

use Core\Router;

$router = new Router();

// Enforce maintenance mode before any route is matched
\App\CMS\Maintenance::check();

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
$router->get('/admin/modules',                                   'Admin\ModulesController', 'index');
$router->post('/admin/modules/install-bundle',                   'Admin\ModulesController', 'installBundle');
$router->post('/admin/modules/([a-z0-9\-\_]+)/toggle',            'Admin\ModulesController', 'toggle');
$router->post('/admin/modules/([a-z0-9\-\_]+)/install',           'Admin\ModulesController', 'install');
$router->post('/admin/modules/([a-z0-9\-\_]+)/uninstall',         'Admin\ModulesController', 'uninstall');
$router->post('/admin/modules/([a-z0-9\-\_]+)/sync-views',        'Admin\ModulesController', 'syncViews');

// ── Themes ────────────────────────────────────────────────────────────────────
$router->get('/admin/themes',                'Admin\ThemesController', 'index');
$router->post('/admin/themes/set-theme',     'Admin\ThemesController', 'setTheme');

// ── Profile ───────────────────────────────────────────────────────────────────
$router->get('/admin/profile',               'Admin\ProfileController', 'index');
$router->post('/admin/profile/update',       'Admin\ProfileController', 'update');

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

// ── Non-core module routes (loaded from DB, only when CMS is installed) ────────
\App\CMS\ModuleManager::loadRoutes($router);

// ── Front-end placeholder ──────────────────────────────────────────────────────
$router->add('GET', '/', 'Web\Welcome', 'index');

return $router;
