<?php
/**
 * Example: Working with Site Settings
 *
 * Shows how to read and write settings via SettingModel,
 * and how to add new settings keys for a custom module.
 */

use App\Models\SettingModel;

$settings = new SettingModel();

// -- Reading settings ----------------------------------------------------------

$siteName    = $settings->get('site_name');
$siteUrl     = $settings->get('site_url');
$timezone    = $settings->get('timezone');
$maintenance = $settings->get('maintenance_mode'); // '1' or '0'

// With a default fallback
$language = $settings->get('default_language') ?? 'en';

// -- Writing settings ----------------------------------------------------------

$settings->set('site_name', 'My Awesome Site');
$settings->set('maintenance_mode', '1'); // enable maintenance

// -- Adding custom settings in a module install() ------------------------------

/*
// Inside your Module::install(Connection $db):

$db->table('settings')->insertBatch([
    [
        'key'   => 'portfolio_per_page',
        'value' => '12',
        'type'  => 'number',
        'grp'   => 'portfolio',
        'label' => 'Projects Per Page',
    ],
    [
        'key'   => 'portfolio_layout',
        'value' => 'grid',
        'type'  => 'select',
        'grp'   => 'portfolio',
        'label' => 'Default Layout',
    ],
]);
*/

// -- Removing custom settings in uninstall() -----------------------------------

/*
// Inside your Module::uninstall(Connection $db):
$db->table('settings')->where('grp', 'portfolio')->delete()->run();
*/

// -- Adding settings to the Admin UI ------------------------------------------

/*
// 1. Add the field to App/Views/admin/settings/index.php:

<div class="form-group">
    <label for="portfolio_per_page">Projects Per Page</label>
    <input
        type="number"
        id="portfolio_per_page"
        name="portfolio_per_page"
        class="form-control"
        value="{{ $settings['portfolio_per_page'] ?? 12 }}"
    >
</div>

// 2. Whitelist the key in SettingsController (App/Controllers/Admin/SettingsController.php):

private const ALLOWED_KEYS = [
    'site_name', 'site_url', 'site_description', 'admin_email',
    'default_language', 'timezone', 'date_format', 'time_format',
    'maintenance_mode',
    // Add your module's keys:
    'portfolio_per_page', 'portfolio_layout',
];

// Only whitelisted keys are processed by the save() action.
// Any key not in ALLOWED_KEYS is silently ignored.
*/

// -- Using settings in a controller -------------------------------------------

/*
class ProjectsController extends BaseController
{
    public function index(): void
    {
        $this->requirePermission('projects.view');

        $settings = new SettingModel();
        $perPage  = (int) ($settings->get('portfolio_per_page') ?? 12);

        $page   = (int) ($this->input->get('page') ?: 1);
        $offset = ($page - 1) * $perPage;

        $projects = $this->db
            ->table('portfolio_projects')
            ->where('status', 'published')
            ->orderBy('created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        $this->adminRender('modules/portfolio/admin/projects/index', compact('projects'), 'Projects', 'portfolio');
    }
}
*/
