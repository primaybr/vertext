<?php

declare(strict_types=1);

namespace App\CMS;

use Core\Model;

/**
 * Minimal shortcode resolver for trusted content bodies (Pages, Blog posts).
 *
 * Supported:
 *   [form slug="contact"]   - embeds a Forms Builder form inline
 *   [newsletter_signup]     - embeds a newsletter subscribe box
 *
 * Shortcodes are resolved AFTER the body HTML is considered trusted (both
 * Pages and Blog bodies are admin-authored Quill HTML rendered with {!! !!}),
 * so the replacement HTML follows the same trust model.
 */
class Shortcodes
{
    /** Resolve all supported shortcodes in a trusted HTML body. */
    public static function render(string $html, string $baseUrl = ''): string
    {
        if ($html === '' || strpos($html, '[') === false) {
            return $html;
        }

        if (strpos($html, '[form') !== false) {
            $html = (string) preg_replace_callback(
                '/\[form\s+slug=["\']([a-z0-9\-]+)["\']\s*\]/i',
                static function (array $m) use ($baseUrl): string {
                    return self::renderForm($m[1], $baseUrl);
                },
                $html
            );
        }

        if (strpos($html, '[newsletter_signup') !== false) {
            $html = (string) preg_replace(
                '/\[newsletter_signup\s*\]/i',
                self::renderNewsletterSignup($baseUrl),
                $html
            );
        }

        return $html;
    }

    /** Render the inline newsletter signup box or an empty string. */
    private static function renderNewsletterSignup(string $baseUrl): string
    {
        if (!ModuleLoader::isEnabled('newsletter')) {
            return '';
        }

        $partial = ROOT . 'App' . DS . 'Views' . DS . 'modules' . DS . 'newsletter' . DS . 'front' . DS . '_signup.php';
        if (!is_file($partial)) {
            return '';
        }

        $session    = new \Core\Http\Session();
        $csrf       = new \Core\Security\CSRF($session);
        $csrf_token = $csrf->getToken();
        $flashRaw   = $session->flash('newsletter_flash');
        $flash      = is_array($flashRaw) ? $flashRaw : [];

        ob_start();
        include $partial;
        return (string) ob_get_clean();
    }

    /** Render a form embed (same partial as /forms/{slug}) or an empty string. */
    private static function renderForm(string $slug, string $baseUrl): string
    {
        if (!ModuleLoader::isEnabled('forms')) {
            return '';
        }

        try {
            $form = (new Model('form_definitions'))
                ->where('slug', $slug)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->get(1);
        } catch (\Throwable $e) {
            return '';
        }

        if (!$form) {
            return '<!-- form "' . htmlspecialchars($slug) . '" not found -->';
        }

        $partial = ROOT . 'App' . DS . 'Views' . DS . 'modules' . DS . 'forms' . DS . 'front' . DS . '_embed.php';
        if (!is_file($partial)) {
            return '';
        }

        $session = new \Core\Http\Session();
        $csrf    = new \Core\Security\CSRF($session);

        // Same context the FormFrontController builds for the standalone page
        $fields     = json_decode($form['fields'] ?: '[]', true) ?: [];
        $flashRaw   = $session->flash('form_flash_' . $slug);
        $flash      = is_array($flashRaw) ? $flashRaw : [];
        $csrf_token = $csrf->getToken();
        $member     = null;
        if (ModuleLoader::isEnabled('members') && SiteAuth::check()) {
            $member = SiteAuth::user();
        }

        $mathQuestion = null;
        $settings = json_decode($form['settings'] ?: '{}', true) ?: [];
        if (!empty($settings['math_challenge'])) {
            $a = random_int(2, 9);
            $b = random_int(2, 9);
            $session->set('form_math_' . $form['id'], (string) ($a + $b));
            $mathQuestion = "{$a} + {$b}";
        }

        ob_start();
        include $partial;
        return (string) ob_get_clean();
    }
}
