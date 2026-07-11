<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use Core\Controller as Controller;
use App\CMS\Installer;

class Welcome extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        // Not installed yet - send to setup wizard
        if (!Installer::isInstalled()) {
            header('Location: ' . $this->baseUrl . '/setup');
            exit;
        }

        // Fetch site settings from DB
        $settings = [];
        try {
            $rows = (new \Core\Model('settings'))->get() ?: [];
            foreach ($rows as $row) {
                $settings[$row['key']] = $row['value'];
            }
        } catch (\Exception) {
            // DB unavailable - fall through to defaults
        }

        $landingBlocksEnabled = ($settings['landing_blocks_enabled'] ?? '0') === '1';

        if ($landingBlocksEnabled && class_exists(\App\Modules\ThemeCustomizer\LandingBlocksHelper::class)) {
            $theme  = \App\Theme\ThemeEngine::activeTheme();
            $blocks = \App\Modules\ThemeCustomizer\LandingBlocksHelper::getBlocks($theme);

            \App\Theme\ThemeEngine::render('modules/theme-customizer/front/landing/index', [
                'blocks'           => $blocks,
                'baseUrl'          => $this->baseUrl,
                'assetsUrl'        => $this->assetsUrl,
                'page_description' => $settings['site_description'] ?? '',
            ]);
            return;
        }

        $this->render('default/landing', [
            'siteName'        => $settings['site_name']        ?? 'My Site',
            'siteDescription' => $settings['site_description'] ?? '',
            'baseUrl'         => $this->baseUrl,
            'assetsUrl'       => $this->assetsUrl,
        ]);
    }

    /**
     * Handle POST request data
     *
     * @return void
     */
    public function postData(): void
    {
        // Handle POST request data
        $postData = $_POST;
        // Process the data as needed
        // ...
        // Return a response
        $response = [
            'success' => true,
            'message' => 'Data received successfully',
            'data' => $postData,
        ];

        echo json_encode($response);
    }
}
