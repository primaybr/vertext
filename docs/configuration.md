# Configuration

Vertext uses layered configuration. Base settings live in `Config/`, and runtime overrides (written by the setup wizard) live in `Storage/`.

## Config Files

### Config/Config.php — Application Settings

The main config file. **Do not commit** user-specific overrides here; use `Storage/app.php` instead.

```php
return [
    'env'         => 'development',       // 'development' | 'production'
    'https'       => false,               // true if site uses HTTPS
    'baseUrl'     => 'http://localhost',  // base URL (no trailing slash)
    'siteName'    => 'Vertext',
    'description' => '',
    'assetUrl'    => '/assets',
    'version'     => '0.0.1-alpha',
];
```

| Key | Description |
|-----|-------------|
| `env` | `development` shows stack traces; `production` hides them |
| `https` | Enables HTTPS session cookies and `Secure` flag |
| `baseUrl` | Used by `URI::redirect()` and link generation |
| `version` | CMS version string (shown in admin footer) |

### Storage/app.php — Runtime Site Settings

Written by the setup wizard. Merges over `Config/Config.php`. Do not hand-edit unless necessary.

```php
return [
    'site' => [
        'title'   => 'My Site',
        'baseUrl' => 'https://mysite.test',
    ],
];
```

### Storage/db.php — Database Credentials

Written by the setup wizard. Never commit this file (already in `.gitignore`).

```php
return [
    'driver'   => 'pgsql',
    'host'     => 'localhost',
    'port'     => '5432',
    'database' => 'vertext',
    'username' => 'vertext_user',
    'password' => 'secret',
    'charset'  => 'utf8',
    'prefix'   => '',
];
```

### Config/Routes.php — Route Definitions

Registers all application routes. Core admin routes are here. Module routes are loaded dynamically via `ModuleManager::loadRoutes($router)`. Add custom public routes at the bottom.

```php
// Add a custom route
$router->get('/contact', 'Web\ContactController', 'index');
```

### Config/Database.php — Database Config Loader

Loads `Storage/db.php` when installed, or falls back to defaults. You generally never touch this file.

## Runtime Settings (Database-Stored)

Site-wide settings managed through **Admin → Settings** are stored in the `settings` table and accessed anywhere via:

```php
use App\Models\SettingModel;

$setting = new SettingModel();
$value = $setting->get('site_name');
$setting->set('site_name', 'My New Title');
```

### Default Settings Keys

| Key | Description |
|-----|-------------|
| `site_name` | Site display name |
| `site_url` | Canonical site URL |
| `site_description` | Meta description |
| `admin_email` | Admin contact email |
| `default_language` | Language code (e.g. `en`) |
| `timezone` | PHP timezone string (e.g. `Asia/Jakarta`) |
| `date_format` | Date display format |
| `time_format` | Time display format |
| `maintenance_mode` | `1` or `0` — enables/disables the site for non-admins |

### Adding Custom Settings

1. Register them via a migration in `Migrations/` or your module's `install()` method.
2. Insert a row into the `settings` table with your key/value/type/group.
3. Optionally add a UI field in `App/Views/admin/settings/index.php`.
4. Whitelist the key in `SettingsController::ALLOWED_KEYS` (in `App/Controllers/Admin/SettingsController.php`).

## Trusted Proxy Configuration

If your server sits behind a load balancer or Cloudflare, configure trusted proxy IPs so that `getIpAddress()` reads forwarding headers correctly:

```php
// In Public/index.php (bootstrap) or a middleware
use Core\Http\Client;
Client::setTrustedProxies(['10.0.0.1', '192.168.1.100']);
```

Without this, `REMOTE_ADDR` is always used (the secure default).

## Cache

The cache directory is `Cache/`. Clear it after any view, CSS, or JS change:

```
Admin → Settings → Clear Cache
```

Or manually delete all files in `Cache/` (leave the directory itself in place).
