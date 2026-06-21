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
        // Not installed yet — send to setup wizard
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
            // DB unavailable — fall through to defaults
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
