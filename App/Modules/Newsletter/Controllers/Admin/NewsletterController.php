<?php

declare(strict_types=1);

namespace App\Modules\Newsletter\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;

/**
 * Newsletter admin controller.
 *
 * GET /admin/newsletter → index()
 */
class NewsletterController extends BaseController
{
    protected string $module = 'newsletter';

    public function __construct()
    {
        parent::__construct();
    }

    /** GET /admin/newsletter */
    public function index(): void
    {
        $this->requirePermission('newsletter.view');

        $this->adminRender('admin/profile/index', [
            // TODO: pass data from DB
        ], 'Newsletter', 'newsletter');
    }
}