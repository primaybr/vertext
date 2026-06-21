# Getting Started with Vertext CMS

Vertext is a modular PHP CMS designed for developers. It provides a full admin panel, role-based access control, an extensible module system, and a clean architecture for building content-driven applications.

## Requirements

- PHP 8.2 or higher
- PostgreSQL 13+
- PDO extension with PDO_PGSQL driver
- PHP extensions: `json`, `mbstring`, `fileinfo`
- A web server with URL rewrite support (Apache with `mod_rewrite`, or Nginx)

## Installation

### 1. Download / Clone

Place the project in your web server's document root. The public entry point is `Public/index.php`.

**Apache** — point your virtual host `DocumentRoot` to the `Public/` directory. The included `.htaccess` handles URL rewriting.

**Nginx** — configure your server block:
```nginx
root /path/to/vertext/Public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 2. Create a PostgreSQL Database

```sql
CREATE DATABASE vertext;
CREATE USER vertext_user WITH PASSWORD 'your_password';
GRANT ALL PRIVILEGES ON DATABASE vertext TO vertext_user;
```

### 3. Run the Setup Wizard

Open your browser and navigate to `http://your-domain/setup`. The wizard guides you through five steps:

| Step | What happens |
|------|--------------|
| 1 — Requirements | Checks PHP version, extensions, and directory permissions |
| 2 — Database | Enter your PostgreSQL credentials; wizard creates all tables |
| 3 — Admin User | Create the initial administrator account |
| 4 — Site Config | Set site name, URL, and timezone |
| 5 — Complete | CMS is installed; redirected to login |

After completion, `Storage/installed.lock` and `Storage/db.php` are written. The setup wizard is automatically disabled once these files exist.

### 4. Log In

Navigate to `/admin/login` and sign in with the admin credentials you created in step 3.

## First Steps After Installation

1. **Explore the Dashboard** — `/admin` shows system stats and recent activity.
2. **Install modules** — Go to **Admin → Modules** and install Blog and Media.
3. **Create users** — Go to **Admin → Users** to add additional team members.
4. **Configure roles** — Go to **Admin → Roles** to create custom roles with granular permissions.
5. **Adjust settings** — Go to **Admin → Settings** to update site name, URL, and timezone.

## Development vs Production

The environment is controlled by `APP_ENV` in `Config/Config.php`:

```php
'env' => 'development', // or 'production'
```

In `development`, full error stack traces are shown. In `production`, generic error pages are shown and detailed errors are written to `Logs/`.

## Directory Overview

```
vertext/
├── App/                  # Your application code
│   ├── CMS/              # Core CMS helpers (Auth, Installer, ModuleManager)
│   ├── Controllers/      # Admin, Setup, and Web controllers
│   ├── Models/           # Database models
│   ├── Modules/          # Installable modules (Blog, Media, ...)
│   └── Views/            # Template files
├── Core/                 # Framework internals — do not modify
├── Config/               # Application config (Routes, Database, paths)
├── Public/               # Web root — only this directory is web-accessible
│   ├── assets/           # CSS, JS, images
│   └── index.php         # Front controller
├── Storage/              # Runtime-generated config (db.php, app.php) — gitignored
├── Cache/                # Query and template cache files
├── Logs/                 # Application logs
└── Migrations/           # Database schema files
```

## Next Steps

- [Configuration](configuration.md) — all config keys explained
- [Admin Guide](admin-guide.md) — managing users, roles, and modules
- [Module System](module-system.md) — how modules work
- [Creating a Module](creating-a-module.md) — build your own module
