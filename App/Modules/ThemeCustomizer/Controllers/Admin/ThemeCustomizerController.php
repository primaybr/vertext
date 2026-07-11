<?php

declare(strict_types=1);

namespace App\Modules\ThemeCustomizer\Controllers\Admin;

use App\Controllers\Admin\BaseController;
use App\CMS\Auth;
use App\Modules\ThemeCustomizer\ThemeCustomizerHelper;
use App\Modules\ThemeCustomizer\LandingBlocksHelper;
use App\Theme\ThemeEngine;

class ThemeCustomizerController extends BaseController
{
    protected string $module = 'theme-customizer';

    private const GRP = 'theme-customizer';

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requirePermission('theme-customizer.manage');

        $rows     = $this->db('settings')->where('grp', self::GRP)->get() ?: [];
        $settings = array_column($rows, 'value', 'key');

        $activeTheme = ThemeEngine::activeTheme();
        $themes      = ThemeEngine::discover();
        $blocks      = LandingBlocksHelper::getBlocks($activeTheme);

        $this->adminRender('modules/theme-customizer/admin/theme-customizer/index', [
            'settings'    => $settings,
            'activeTheme' => $activeTheme,
            'themes'      => $themes,
            'blocks'      => $blocks,
            'blockTypes'  => LandingBlocksHelper::TYPES,
        ], 'Theme Customizer', 'theme-customizer');
    }

    public function save(): void
    {
        $this->requirePermission('theme-customizer.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->flash('error', 'Security token invalid.');
            $this->redirect('/admin/theme-customizer');
            return;
        }

        // Read raw (unsanitized): each of these is validated/allowlisted immediately
        // below, or (custom_css) is CSS text rather than HTML - htmlspecialchars()'s
        // default sanitization was silently corrupting any value containing a quote
        // or ampersand (every font choice except "system" has a quote in its CSS
        // value; any logo URL with a query string has an ampersand; most real CSS has
        // both), which was never providing safety here, just breaking legitimate input.
        $primaryColor         = trim($this->input->post('primary_color', false) ?? '#1E3A5F');
        $fontFamily           = trim($this->input->post('font_family', false)   ?? 'system');
        $cornerStyle          = trim($this->input->post('corner_style', false)  ?? 'subtle');
        $logoUrl              = trim($this->input->post('logo_url', false)      ?? '');
        $customCss            = $this->input->post('custom_css', false) ?? '';
        $landingBlocksEnabled = $this->input->post('landing_blocks_enabled') ? '1' : '0';

        // Validate hex color
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
            $primaryColor = '#1E3A5F';
        }

        if (!in_array($cornerStyle, ['sharp', 'subtle', 'rounded'], true)) {
            $cornerStyle = 'subtle';
        }

        // Strip logo URL to relative paths or https only - also rejects protocol-relative
        // //host URLs, which the old inline regex here let through as if they were relative.
        $logoUrl = \App\CMS\HtmlSanitizer::isSafeUrl($logoUrl);

        $updates = [
            'primary_color'          => $primaryColor,
            'font_family'            => $fontFamily,
            'corner_style'           => $cornerStyle,
            'logo_url'               => $logoUrl,
            'custom_css'             => $customCss,
            'landing_blocks_enabled' => $landingBlocksEnabled,
        ];

        foreach ($updates as $key => $val) {
            $row = $this->db('settings')
                ->select('id')->where('key', $key)->where('grp', self::GRP)->get(1);
            if ($row) {
                $this->db('settings')->where('id', $row['id'])->save(['value' => $val], true);
            } else {
                $this->db('settings')->save(['key' => $key, 'value' => $val, 'grp' => self::GRP]);
            }
        }

        ThemeCustomizerHelper::regenerateCustomCssFile();

        Auth::audit('theme-customizer.save', 'settings', '');
        $this->flash('success', 'Theme settings saved.');
        $this->redirect('/admin/theme-customizer');
    }

    /**
     * GET /admin/theme-customizer/preview?primary_color=&font_family=&corner_style=
     * Renders the active theme with pending (unsaved) settings applied, for the
     * live-preview iframe. Nothing is persisted here.
     */
    public function preview(): void
    {
        $this->requirePermission('theme-customizer.manage');

        // This action renders straight through ThemeEngine::render() instead of
        // adminRender(), so it never picks up BaseController's CSP override - it's left
        // with Core\Middleware\SecurityHeadersMiddleware's global baseline, which sends
        // `X-Frame-Options: DENY` and `frame-ancestors 'none'` unconditionally. Both
        // headers block ANY framing, including the same-origin <iframe> on the Theme
        // Customizer page that embeds this exact URL. Re-emitted here with framing
        // relaxed to same-origin only - header() replaces the earlier same-named
        // header, everything else (script-src's hashes, no unsafe-inline) stays as
        // strict as the front-end baseline, since this route renders the same
        // front-end theme templates that already work under that policy.
        //
        // script-src allow-lists two static inline scripts by SHA-256 hash:
        // theme-init.php's FOUC-prevention snippet (same hash the framework's own
        // SecurityHeadersMiddleware allow-lists), and this action's link-click guard
        // (App/Themes/*/layout.php, gated on $data['preview']) - clicking a real nav
        // link inside the preview iframe would otherwise navigate it to a normal
        // front-end page, which has no frame-ancestors override and would go blank
        // for the exact same reason this whole header block exists.
        if (!headers_sent()) {
            header('X-Frame-Options: SAMEORIGIN');
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'sha256-oDYWwGoPMMLZnC4nXKXi7EA6Ad5mbokl8Ye1cMFUfJk=' 'sha256-Ev0dw5URHlj3OECNESdfASmS3fVb1qtfB0hhcZoJO+Q='; style-src 'self'; img-src 'self' data: blob:; font-src 'self' data:; frame-ancestors 'self'");
        }

        $overrides = [];

        $primaryColor = $this->input->get('primary_color');
        if ($primaryColor !== null && preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
            $overrides['primary_color'] = $primaryColor;
        }

        // Raw (unsanitized): font values like "Georgia, 'Times New Roman', serif"
        // contain a quote that htmlspecialchars() would mangle into &apos;, corrupting
        // the CSS before it's even checked - there's no HTML context here to protect.
        $fontFamily = $this->input->get('font_family', false);
        if ($fontFamily !== null) {
            $overrides['font_family'] = $fontFamily;
        }

        $cornerStyle = $this->input->get('corner_style');
        if ($cornerStyle !== null && in_array($cornerStyle, ['sharp', 'subtle', 'rounded'], true)) {
            $overrides['corner_style'] = $cornerStyle;
        }

        ThemeCustomizerHelper::setPreviewOverrides($overrides);

        // Landing Blocks tab requests the real landing page template (same one
        // Welcome::index() renders live) instead of the generic style-demo page,
        // so its preview actually shows the blocks being edited, not just colors/
        // fonts on placeholder content.
        if ($this->input->get('view') === 'blocks') {
            // Pending, unsaved landing-blocks edits staged by
            // previewStageLandingBlocks() in a prior request (see that method's
            // docblock for why this needs a session round-trip rather than the
            // same-request static ThemeCustomizerHelper::setPreviewOverrides() uses
            // above).
            $activeTheme  = ThemeEngine::activeTheme();
            $stagedBlocks = $this->session->get('tc_preview_blocks_' . $activeTheme);
            if (is_array($stagedBlocks)) {
                LandingBlocksHelper::setPreviewOverride($stagedBlocks);
            }

            ThemeEngine::render('modules/theme-customizer/front/landing/index', [
                'blocks'     => LandingBlocksHelper::getBlocks($activeTheme),
                'baseUrl'    => $this->baseUrl,
                'page_title' => 'Live Preview',
                'preview'    => true,
            ]);
            return;
        }

        ThemeEngine::render('modules/theme-customizer/front/preview-demo', [
            'baseUrl'    => $this->baseUrl,
            'page_title' => 'Live Preview',
            'preview'    => true,
        ]);
    }

    /**
     * POST /admin/theme-customizer/landing-blocks/([a-z0-9\-]+)/preview-stage
     * Stages a pending (unsaved) blocks array in session for the live-preview
     * iframe to pick up on its next reload - sanitized exactly like Save, but
     * never touches the database. Unlike the color/font/corner-style overrides
     * above (which round-trip via query string in one GET), a blocks payload
     * (rich-text HTML, multiple items, image URLs) is far too large for a URL,
     * so staging and rendering happen as two separate requests: this one writes
     * to session, preview() above reads it back.
     */
    public function previewStageLandingBlocks(string $themeSlug): void
    {
        $this->requirePermission('theme-customizer.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }

        $validSlugs = array_column(ThemeEngine::discover(), 'slug');
        if (!in_array($themeSlug, $validSlugs, true)) {
            $this->json(['success' => false, 'message' => 'Unknown theme.'], 404);
        }

        // Raw (unsanitized): a JSON-encoded payload is mostly double-quote
        // characters - htmlspecialchars() would mangle every one of them into
        // &quot; before json_decode() ever saw it, making decoding fail on any
        // non-trivial payload. sanitizeBlocks() below is the real validation.
        $raw    = $this->input->post('blocks', false) ?? '[]';
        $blocks = json_decode(is_string($raw) ? $raw : '[]', true);
        if (!is_array($blocks)) {
            $this->json(['success' => false, 'message' => 'Invalid blocks payload.'], 400);
        }

        $clean = LandingBlocksHelper::sanitizeBlocks($blocks);
        $this->session->set('tc_preview_blocks_' . $themeSlug, $clean);

        $this->json(['success' => true]);
    }

    /**
     * POST /admin/theme-customizer/landing-blocks/([a-z0-9\-]+)/save
     * Whole-array save of one theme's landing blocks, mirroring the Forms
     * builder's saveFields() pattern - the client holds the full array in
     * memory and PUTs it back in one shot on every Save click.
     */
    public function saveLandingBlocks(string $themeSlug): void
    {
        $this->requirePermission('theme-customizer.manage');

        $token = $this->input->post('csrf_token') ?? '';
        if (!$this->csrf->validateToken($token)) {
            $this->json(['success' => false, 'message' => 'Security token invalid.'], 403);
        }

        $validSlugs = array_column(ThemeEngine::discover(), 'slug');
        if (!in_array($themeSlug, $validSlugs, true)) {
            $this->json(['success' => false, 'message' => 'Unknown theme.'], 404);
        }

        // Raw (unsanitized): see previewStageLandingBlocks() below for why -
        // htmlspecialchars() on a JSON string corrupts every double-quote,
        // so json_decode() below silently failed on any non-trivial payload.
        $raw    = $this->input->post('blocks', false) ?? '[]';
        $blocks = json_decode(is_string($raw) ? $raw : '[]', true);
        if (!is_array($blocks)) {
            $this->json(['success' => false, 'message' => 'Invalid blocks payload.'], 400);
        }

        $clean = LandingBlocksHelper::sanitizeBlocks($blocks);
        LandingBlocksHelper::saveBlocks($themeSlug, $clean);

        Auth::audit('theme-customizer.landing_blocks_saved', 'theme_landing_blocks', $themeSlug, ['block_count' => count($clean)]);

        $this->json(['success' => true, 'message' => 'Landing blocks saved.', 'block_count' => count($clean)]);
    }
}
