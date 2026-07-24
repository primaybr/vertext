<?php

declare(strict_types=1);

namespace App\CMS;

/**
 * Lightweight i18n helper.
 *
 * Keys use dot-notation: "admin.save_changes" loads
 * App/Lang/{locale}/admin.php and looks up key "save_changes".
 * Keys without a dot are looked up in App/Lang/{locale}/app.php.
 *
 * Usage:
 *   I18n::setLocale('id');
 *   echo I18n::get('admin.save_changes');   // "Simpan Perubahan"
 *   echo __('admin.save_changes');           // same via global helper
 *   echo I18n::date(time(), 'long');         // locale-aware date
 */
class I18n
{
    private static ?string $locale = null;
    private static array   $cache  = [];

    private const FALLBACK = 'en';

    // -- Locale management -----------------------------------------------------

    public static function setLocale(string $locale): void
    {
        $locale    = strtolower(preg_replace('/[^a-zA-Z_\-]/', '', $locale));
        $supported = self::getSupportedLocales();
        if (!in_array($locale, $supported, true)) {
            $locale = self::FALLBACK;
        }
        self::$locale = $locale;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['vtx_locale'] = $locale;
        }
    }

    public static function getLocale(): string
    {
        if (self::$locale === null) {
            self::$locale = self::resolveLocale();
        }
        return self::$locale;
    }

    /**
     * The site's configured default locale (settings.default_locale),
     * independent of the current visitor's session override. Themes using a
     * no-prefix-for-default URL scheme (bare path = default locale, /xx/ =
     * everything else) use this to know which locale gets the bare path.
     */
    public static function getDefaultLocale(): string
    {
        try {
            $row = self::pdo()
                ->query("SELECT value FROM settings WHERE key = 'default_locale' LIMIT 1")
                ->fetchColumn();
            if ($row && in_array($row, self::getSupportedLocales(), true)) {
                return (string) $row;
            }
        } catch (\Throwable) {
            // DB unavailable (e.g. setup wizard) - fall through to default
        }
        return self::FALLBACK;
    }

    /**
     * Build a same-site URL that carries the CURRENT visitor's locale prefix
     * (bare path for the default locale, /xx/ for everything else) - the one
     * place every internal page link should route through, so a visitor who
     * has switched locale stays in it as they navigate the site, instead of
     * reverting to the default locale's bare URL on their very next click
     * (session-sticky locale still applies content-wise, but the URL itself
     * would silently stop matching what's actually being shown).
     *
     * Not for asset URLs (CSS/JS/images) - those must never carry a locale
     * prefix, so callers building those keep using $baseUrl directly.
     */
    public static function path(string $baseUrl, string $path): string
    {
        $locale  = self::getLocale();
        $default = self::getDefaultLocale();
        $base    = rtrim($baseUrl, '/');
        $path    = '/' . ltrim($path, '/');
        return $locale === $default ? $base . $path : $base . '/' . $locale . $path;
    }

    /** Returns all locale codes found in App/Lang/ directories. */
    public static function getSupportedLocales(): array
    {
        static $locales = null;
        if ($locales === null) {
            $locales = [self::FALLBACK];
            $dir     = self::langDir();
            if (is_dir($dir)) {
                foreach (scandir($dir) as $entry) {
                    if ($entry === '.' || $entry === '..' || $entry === self::FALLBACK) {
                        continue;
                    }
                    if (is_dir($dir . $entry)) {
                        $locales[] = $entry;
                    }
                }
            }
        }
        return $locales;
    }

    // -- Translation lookup ----------------------------------------------------

    /**
     * Resolve a translation key. Returns the key itself as fallback.
     *
     * @param string $key          dot-notation, e.g. "admin.save" or "app.no_results"
     * @param array  $replacements keyed by placeholder name, e.g. ['name' => 'Post']
     */
    public static function get(string $key, array $replacements = []): string
    {
        if ($key === '') {
            return '';
        }

        [$file, $subKey] = self::parseKey($key);
        $locale = self::getLocale();

        $text = self::lookup($locale, $file, $subKey)
             ?? self::lookup(self::FALLBACK, $file, $subKey)
             ?? $key;

        if ($replacements) {
            foreach ($replacements as $placeholder => $value) {
                $text = str_replace(':' . $placeholder, (string) $value, $text);
            }
        }

        return $text;
    }

    // -- Date formatting -------------------------------------------------------

    /**
     * Format a timestamp in the current locale.
     * Uses IntlDateFormatter if ext/intl is available, falls back to date().
     *
     * @param int|string $timestamp Unix timestamp or strtotime-parseable string
     * @param string     $format    'short' | 'medium' | 'long' | 'full'
     */
    public static function date(int|string $timestamp, string $format = 'medium'): string
    {
        if (is_string($timestamp)) {
            $timestamp = strtotime($timestamp) ?: 0;
        }

        $locale = self::getLocale();

        if (class_exists(\IntlDateFormatter::class)) {
            $styles = [
                'short'  => \IntlDateFormatter::SHORT,
                'medium' => \IntlDateFormatter::MEDIUM,
                'long'   => \IntlDateFormatter::LONG,
                'full'   => \IntlDateFormatter::FULL,
            ];
            $style = $styles[$format] ?? \IntlDateFormatter::MEDIUM;
            $fmt   = new \IntlDateFormatter($locale, $style, \IntlDateFormatter::NONE);
            $out   = $fmt->format($timestamp);
            return $out !== false ? $out : date('Y-m-d', $timestamp);
        }

        // PHP date() fallback
        $formats = [
            'short'  => 'd/m/Y',
            'medium' => 'd M Y',
            'long'   => 'd F Y',
            'full'   => 'l, d F Y',
        ];
        return date($formats[$format] ?? 'd M Y', $timestamp);
    }

    // -- Schema migration ------------------------------------------------------

    /**
     * Add lang column to pages and posts tables, seed default_locale setting.
     * Safe to call repeatedly - all operations use IF NOT EXISTS guards.
     * Called by SettingsController::runMigration() and from the CLI.
     */
    public static function migrate(): void
    {
        try {
            $pdo = self::pdo();

            foreach (['posts', 'pages'] as $table) {
                try {
                    $col = $pdo->query(
                        "SELECT 1 FROM information_schema.columns
                         WHERE table_schema = 'public'
                           AND table_name   = '{$table}'
                           AND column_name  = 'lang' LIMIT 1"
                    )->fetchColumn();

                    if (!$col) {
                        $pdo->exec(
                            "ALTER TABLE {$table}
                             ADD COLUMN IF NOT EXISTS lang VARCHAR(10) NOT NULL DEFAULT 'en'"
                        );
                    }
                } catch (\Throwable) {
                    // Table may not be installed yet; silently skip.
                }
            }

            // Seed default_locale in settings
            $exists = $pdo->query(
                "SELECT 1 FROM settings WHERE key = 'default_locale' LIMIT 1"
            )->fetchColumn();

            if (!$exists) {
                $pdo->exec(
                    "INSERT INTO settings (key, value, type, grp, label, created_at, updated_at)
                     VALUES ('default_locale', 'en', 'string', 'i18n', 'Default Locale', NOW(), NOW())"
                );
            }
        } catch (\Throwable) {
            // Non-fatal; migration will succeed on next explicit call.
        }
    }

    // -- Private helpers -------------------------------------------------------

    private static function langDir(): string
    {
        return ROOT . 'App' . DS . 'Lang' . DS;
    }

    private static function parseKey(string $key): array
    {
        $pos = strpos($key, '.');
        if ($pos !== false) {
            return [substr($key, 0, $pos), substr($key, $pos + 1)];
        }
        return ['app', $key];
    }

    private static function lookup(string $locale, string $file, string $key): ?string
    {
        $cacheKey = "{$locale}.{$file}";
        if (!array_key_exists($cacheKey, self::$cache)) {
            $path                     = self::langDir() . $locale . DS . $file . '.php';
            self::$cache[$cacheKey]   = is_file($path) ? (require $path) : [];
        }
        $translations = self::$cache[$cacheKey];
        return isset($translations[$key]) ? (string) $translations[$key] : null;
    }

    private static function resolveLocale(): string
    {
        // 1. Session override (user explicitly switched)
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['vtx_locale'])) {
            $loc = (string) $_SESSION['vtx_locale'];
            if (in_array($loc, self::getSupportedLocales(), true)) {
                return $loc;
            }
        }

        // 2. Site default from settings table
        return self::getDefaultLocale();
    }

    private static function pdo(): \PDO
    {
        static $pdo = null;
        if ($pdo === null) {
            // Config\Database already knows about the DB_HOST/etc. env-var
            // override (Kubernetes/container deployments), falling back to
            // Storage/db.php for traditional/wizard-based installs - this used
            // to require Storage/db.php directly and fatal in a container
            // where DB config is env-var-driven with no Storage/ at all.
            $cfg = (new \Config\Database())->getConnectionConfig();
            $dsn = "pgsql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']}";
            $pdo = new \PDO($dsn, $cfg['username'], $cfg['password'], [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
        }
        return $pdo;
    }
}
