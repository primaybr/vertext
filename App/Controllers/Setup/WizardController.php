<?php

declare(strict_types=1);

namespace App\Controllers\Setup;

use Core\Controller;
use App\CMS\Installer;

/**
 * Setup Wizard Controller
 * Guides through 5-step initial CMS installation.
 */
class WizardController extends Controller
{
    private const TOTAL_STEPS = 5;

    public function __construct()
    {
        parent::__construct();
    }

    /** GET /setup  - show current wizard step */
    public function index(): void
    {
        // Already installed
        if (Installer::isInstalled()) {
            $this->redirect($this->baseUrl . '/admin/login');
        }

        $step = max(1, min(self::TOTAL_STEPS, (int)($this->session->get('setup_step') ?? 1)));
        $this->renderStep($step);
    }

    /** POST /setup/next  - process step and advance */
    public function next(): void
    {
        if (Installer::isInstalled()) {
            $this->redirect($this->baseUrl . '/admin/login');
        }

        $step = max(1, (int)($this->session->get('setup_step') ?? 1));

        switch ($step) {
            case 1:
                $result = $this->processStep1();
                break;
            case 2:
                $result = $this->processStep2();
                break;
            case 3:
                $result = $this->processStep3();
                break;
            case 4:
                $result = $this->processStep4();
                break;
            case 5:
                $this->redirect($this->baseUrl . '/admin/login');
                return;
            default:
                $result = ['success' => false, 'message' => 'Invalid step'];
        }

        if (isset($result['success']) && $result['success'] === false) {
            $this->session->set('setup_error', $result['message'] ?? 'Unknown error');
            $this->redirect($this->baseUrl . '/setup');
        } else {
            // Advance step
            $this->session->set('setup_step', min(self::TOTAL_STEPS, $step + 1));
            $this->session->set('setup_error', '');
            $this->redirect($this->baseUrl . '/setup');
        }
    }

    /** POST /setup/back  - go to previous step */
    public function back(): void
    {
        if (Installer::isInstalled()) {
            $this->redirect($this->baseUrl . '/admin/login');
        }

        $step = max(1, (int)($this->session->get('setup_step') ?? 1));
        $this->session->set('setup_step', max(1, $step - 1));
        $this->session->set('setup_error', '');
        $this->redirect($this->baseUrl . '/setup');
    }

    /** POST /setup/test-db  - AJAX: test DB connection */
    public function testDb(): void
    {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw, true) ?? [];

        $result = Installer::testConnection(
            $data['host']     ?? '',
            $data['port']     ?? '5432',
            $data['database'] ?? '',
            $data['username'] ?? '',
            $data['password'] ?? ''
        );

        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    // -- Private step processors -------------------------------

    private function processStep1(): array
    {
        $reqs = Installer::checkRequirements();
        foreach ($reqs as $key => $req) {
            if (!$req['pass'] && $key !== 'mbstring') { // mbstring is optional-ish
                return ['success' => false, 'message' => "Requirement failed: {$req['label']}"];
            }
        }
        return ['success' => true];
    }

    private function processStep2(): array
    {
        $host = trim($this->input->post('db_host', false) ?? 'localhost');
        $port = trim($this->input->post('db_port', false) ?? '5432');
        $db   = trim($this->input->post('db_name', false) ?? '');
        $user = trim($this->input->post('db_user', false) ?? '');
        $pass = $this->input->post('db_pass', false) ?? '';

        if (!$host || !$db || !$user) {
            return ['success' => false, 'message' => 'Host, database name, and username are required.'];
        }

        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $db)) {
            return ['success' => false, 'message' => 'Database name must start with a letter or underscore and contain only letters, numbers, and underscores.'];
        }

        $dbConfig = [
            'driver'   => 'pgsql',
            'host'     => $host,
            'port'     => $port,
            'database' => $db,
            'username' => $user,
            'password' => $pass,
            'charset'  => 'utf8',
            'prefix'   => '',
        ];

        // Create database if it does not exist
        $create = Installer::createDatabaseIfNeeded($dbConfig);
        if (!$create['success']) {
            return $create;
        }

        // Verify the connection to the (now-existing) target database
        $test = Installer::testConnection($host, $port, $db, $user, $pass);
        if (!$test['success']) {
            return $test;
        }

        // Save credentials to Storage
        Installer::saveDbConfig($dbConfig);

        // Run migrations and seed
        $migrate = Installer::runMigrations($dbConfig);
        if (!$migrate['success']) {
            return $migrate;
        }

        $this->session->set('setup_db', $dbConfig);
        return ['success' => true];
    }

    private function processStep3(): array
    {
        $siteName = trim($this->input->post('site_name', false) ?? '');
        $siteUrl  = trim($this->input->post('site_url', false) ?? '');
        $timezone = $this->input->post('timezone') ?? 'UTC';
        $lang     = $this->input->post('language') ?? 'en';

        if (!$siteName || !$siteUrl) {
            return ['success' => false, 'message' => 'Site name and URL are required.'];
        }

        $appConfig = [
            'site' => [
                'title'  => $siteName,
                'baseUrl'=> rtrim($siteUrl, '/'),
            ]
        ];

        Installer::saveAppConfig($appConfig);

        // Update DB settings
        $dbConfig = $this->session->get('setup_db');
        if ($dbConfig) {
            Installer::updateSiteSettings($dbConfig, [
                'site_name'        => $siteName,
                'site_url'         => rtrim($siteUrl, '/'),
                'timezone'         => $timezone,
                'default_language' => $lang,
            ]);
        }

        $this->session->set('setup_app', compact('siteName', 'siteUrl', 'timezone', 'lang'));
        return ['success' => true];
    }

    private function processStep4(): array
    {
        $name            = trim($this->input->post('admin_name', false) ?? '');
        $email           = trim($this->input->post('admin_email', false) ?? '');
        $password        = $this->input->post('admin_password', false) ?? '';
        $confirmPassword = $this->input->post('admin_password_confirm', false) ?? '';

        if (!$name || !$email || !$password) {
            return ['success' => false, 'message' => 'All fields are required.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }

        if ($password !== $confirmPassword) {
            return ['success' => false, 'message' => 'Passwords do not match.'];
        }

        $dbConfig = $this->session->get('setup_db');
        if (!$dbConfig) {
            return ['success' => false, 'message' => 'Database configuration missing. Please restart setup.'];
        }

        $result = Installer::createAdminUser($dbConfig, $name, $email, $password);
        if (!$result['success']) {
            return $result;
        }

        // Write installed.lock - installation is complete!
        Installer::markInstalled();

        $this->session->set('setup_complete', true);
        return ['success' => true];
    }

    // -- Render helpers ----------------------------------------

    private function renderStep(int $step): void
    {
        $error    = $this->session->flash('setup_error') ?? '';
        $reqs     = $step === 1 ? Installer::checkRequirements() : [];
        $allPass  = $step === 1 ? $this->allRequirementsMet($reqs) : true;
        $setupDb  = $this->session->get('setup_db') ?? [];
        $setupApp = $this->session->get('setup_app') ?? [];
        $timezones= $this->getTimezones();

        $content = $this->render('setup/index', [
            'step'      => $step,
            'totalSteps'=> self::TOTAL_STEPS,
            'error'     => $error,
            'reqs'      => $reqs,
            'allPass'   => $allPass,
            'setupDb'   => $setupDb,
            'setupApp'  => $setupApp,
            'timezones' => $timezones,
            'isComplete'=> Installer::isInstalled(),
            'testDbUrl' => $this->baseUrl . '/setup/test-db',
        ], true);

        $this->render('setup/layout', [
            'content'   => $content,
            'step'      => $step,
            'totalSteps'=> self::TOTAL_STEPS,
            'pageTitle' => 'Setup Wizard - Step ' . $step,
        ]);
    }

    private function allRequirementsMet(array $reqs): bool
    {
        foreach ($reqs as $req) {
            if (!$req['pass']) return false;
        }
        return true;
    }

    private function getTimezones(): array
    {
        return array_combine(
            \DateTimeZone::listIdentifiers(),
            \DateTimeZone::listIdentifiers()
        );
    }

}
