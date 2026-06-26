<?php

declare(strict_types=1);

namespace App\CMS;

use App\Models\SettingModel;

/**
 * Maintenance mode gate.
 * Call Maintenance::check() early in the request lifecycle.
 * Admin/setup routes and logged-in admin users are always bypassed.
 */
class Maintenance
{
    public static function check(): void
    {
        if (!Installer::isInstalled()) {
            return;
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

        // Admin and setup paths always bypass (handles base-path installs too)
        if (preg_match('#/(admin|setup)(/|$)#', $uri)) {
            return;
        }

        // Logged-in admin users can still browse the site
        if (Auth::check()) {
            return;
        }

        try {
            $enabled = (bool) (new SettingModel())->getValue('maintenance_mode', false);
        } catch (\Throwable) {
            return;
        }

        if (!$enabled) {
            return;
        }

        http_response_code(503);
        header('Retry-After: 3600');
        header('Content-Type: text/html; charset=UTF-8');

        $view = ROOT . 'App' . DS . 'Views' . DS . 'maintenance.php';
        if (file_exists($view)) {
            require $view;
        } else {
            echo '<!DOCTYPE html><html><head><title>Under Maintenance</title></head>'
               . '<body style="font-family:sans-serif;text-align:center;padding:4rem;">'
               . '<h1>Under Maintenance</h1><p>We\'ll be back soon.</p>'
               . '</body></html>';
        }
        exit;
    }
}
