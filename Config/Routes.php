<?php

declare(strict_types=1);

namespace Config;

use Core\Router;

$router = new Router();

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
$router->post('/admin/users/(\d+)/update',          'Admin\UsersController', 'update');
$router->post('/admin/users/(\d+)/delete',          'Admin\UsersController', 'delete');
$router->get('/admin/users/form',                   'Admin\UsersController', 'createForm');
$router->get('/admin/users/(\d+)/form',             'Admin\UsersController', 'editForm');

// ── Roles ─────────────────────────────────────────────────────────────────────
$router->get('/admin/roles',                        'Admin\RolesController', 'index');
$router->post('/admin/roles/store',                 'Admin\RolesController', 'store');
$router->post('/admin/roles/(\d+)/update',          'Admin\RolesController', 'update');
$router->post('/admin/roles/(\d+)/delete',          'Admin\RolesController', 'delete');
$router->get('/admin/roles/form',                   'Admin\RolesController', 'createForm');
$router->get('/admin/roles/(\d+)/form',             'Admin\RolesController', 'editForm');

// ── Modules ───────────────────────────────────────────────────────────────────
$router->get('/admin/modules',                                   'Admin\ModulesController', 'index');
$router->post('/admin/modules/([a-z0-9\-\_]+)/toggle',            'Admin\ModulesController', 'toggle');
$router->post('/admin/modules/([a-z0-9\-\_]+)/install',           'Admin\ModulesController', 'install');
$router->post('/admin/modules/([a-z0-9\-\_]+)/uninstall',         'Admin\ModulesController', 'uninstall');
$router->post('/admin/modules/([a-z0-9\-\_]+)/sync-views',        'Admin\ModulesController', 'syncViews');

// ── Settings ──────────────────────────────────────────────────────────────────
$router->get('/admin/settings',             'Admin\SettingsController', 'index');
$router->post('/admin/settings/save',       'Admin\SettingsController', 'save');
$router->post('/admin/settings/clear-cache','Admin\SettingsController', 'clearCache');

// ── Non-core module routes (loaded from DB, only when CMS is installed) ────────
\App\CMS\ModuleManager::loadRoutes($router);

// ── Front-end placeholder ──────────────────────────────────────────────────────
$router->add('GET', '/', 'Web\Welcome', 'index');

return $router;
