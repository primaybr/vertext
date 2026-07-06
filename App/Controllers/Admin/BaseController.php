<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Core\Controller;
use App\CMS\Auth;
use App\CMS\Installer;
use App\CMS\ModuleLoader;

/**
 * Base Admin Controller
 * All admin panel controllers extend this.
 * Handles auth checking, flash messages, and common layout data.
 */
abstract class BaseController extends Controller
{
    protected array  $currentUser = [];

    /**
     * Override in child controllers to declare which module this controller belongs to.
     * Slug must match the `slug` column in the `modules` table.
     * Empty string = no module restriction (always accessible).
     */
    protected string $module = '';

    public function __construct()
    {
        parent::__construct();

        // Redirect to setup if not installed
        if (!Installer::isInstalled()) {
            $this->redirect($this->baseUrl . '/setup');
        }

        // Redirect to login if not authenticated
        if (!Auth::check()) {
            $this->redirect($this->baseUrl . '/admin/login');
        }

        // Enforce session revocation: if this session was revoked from
        // another device (or by an admin), end it now.
        if (!\App\CMS\SessionTracker::validate((string) Auth::id())) {
            Auth::logout();
            $this->session->set('flash', ['type' => 'error', 'message' => 'This session has been signed out remotely.']);
            $this->redirect($this->baseUrl . '/admin/login');
        }

        $this->currentUser = Auth::user() ?? [];

        // Enforce module enabled/disabled status
        if ($this->module !== '' && !ModuleLoader::isEnabled($this->module)) {
            $this->flash('error', 'The "' . $this->module . '" module is currently disabled.');
            $this->redirect($this->baseUrl . '/admin/dashboard');
        }
    }

    /** Render admin page: pre-render content into base layout */
    protected function adminRender(string $view, array $data = [], string $pageTitle = '', string $activeMenu = ''): void
    {
        if (!headers_sent()) {
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; frame-ancestors 'none'");
            header('X-Frame-Options: DENY');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
        $flash     = $this->session->flash('flash');
        $csrfToken = $this->csrf->getToken();
        $avatarUrl = \App\CMS\AvatarHelper::url((string) ($this->currentUser['id'] ?? ''), $this->baseUrl);

        // Controller-passed keys take precedence over base defaults.
        // 'flash' uses whichever source is non-empty: if the controller already
        // consumed and passed it, that version wins; otherwise fall back to what
        // adminRender just read from the session.
        $data = array_merge([
            'currentUser' => $this->currentUser,
            'baseUrl'     => $this->baseUrl,
            'assetsUrl'   => $this->assetsUrl,
            'csrf_token'  => $csrfToken,
            'flash'       => is_array($flash) ? $flash : [],
            'avatarUrl'   => $avatarUrl,
        ], $data);

        $content = $this->render($view, $data, true);

        // The layout gets the same resolved flash so the topbar flash banner works
        // regardless of whether the controller or adminRender consumed the session entry.
        $this->render('admin/_layouts/base', [
            'content'     => $content,
            'pageTitle'   => $pageTitle ?: 'Admin',
            'activeMenu'  => $activeMenu,
            'currentUser' => $this->currentUser,
            'flash'       => $data['flash'],
            'csrf_token'  => $csrfToken,
            'avatarUrl'   => $avatarUrl,
        ]);
    }

    /** Return a fresh ORM Model instance for the given table (admin shorthand - returns directly, unlike model() which attaches to $this). */
    protected function db(string $table): \Core\Model
    {
        return new \Core\Model($table);
    }

    /**
     * Build a named-placeholder "IN (...)" fragment plus its matching binds, for use with
     * Model::whereRaw() - e.g. `[$sql, $binds] = $this->buildInClause($ids); ...->whereRaw("id IN ({$sql})", $binds)`.
     * whereRaw() merges bind keys verbatim into the query's bind bag, which requires named
     * (":key") placeholders - passing a plain 0-indexed array of values with positional "?"
     * placeholders (an easy mistake) mixes named and positional params and fails at the PDO layer.
     */
    protected function buildInClause(array $values, string $prefix = 'in'): array
    {
        $binds = [];
        $placeholders = [];
        foreach (array_values($values) as $i => $value) {
            $key = ":{$prefix}_{$i}";
            $placeholders[] = $key;
            $binds[$key] = $value;
        }
        return [implode(',', $placeholders), $binds];
    }

    /** Check permission; redirect to dashboard if denied */
    protected function requirePermission(string $permission): void
    {
        if (!Auth::can($permission)) {
            $this->flash('error', 'You do not have permission to access that resource.');
            $this->redirect($this->baseUrl . '/admin/dashboard');
        }
    }

    /** Render a view as a raw HTML partial (no layout) - for AJAX modal loading */
    protected function renderPartial(string $view, array $data = []): void
    {
        $data['csrf_token'] = $this->csrf->getToken();
        header('Content-Type: text/html; charset=utf-8');
        echo $this->render($view, $data, true);
        exit;
    }
}
