<?php

declare(strict_types=1);

/**
 * Vertext CMS - Core Tables Migration
 * Creates all core database tables for PostgreSQL.
 */
class Migration_001_CoreTables
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        $this->pdo->exec("
            -- Users (created first; audit FK constraints added after via ALTER)
            CREATE TABLE IF NOT EXISTS users (
                id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
                name        VARCHAR(120) NOT NULL,
                email       VARCHAR(180) UNIQUE NOT NULL,
                password    VARCHAR(255) NOT NULL,
                status      VARCHAR(20)  DEFAULT 'active',
                last_login  TIMESTAMP,
                created_at  TIMESTAMP    DEFAULT NOW(),
                updated_at  TIMESTAMP    DEFAULT NOW(),
                deleted_at  TIMESTAMP,
                created_by  UUID,
                updated_by  UUID,
                deleted_by  UUID
            );

            -- Settings (key-value global config)
            CREATE TABLE IF NOT EXISTS settings (
                id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
                key         VARCHAR(120) UNIQUE NOT NULL,
                value       TEXT,
                type        VARCHAR(20)  DEFAULT 'string',
                grp         VARCHAR(60)  DEFAULT 'general',
                label       VARCHAR(160),
                created_at  TIMESTAMP    DEFAULT NOW(),
                updated_at  TIMESTAMP    DEFAULT NOW(),
                deleted_at  TIMESTAMP,
                created_by  UUID,
                updated_by  UUID,
                deleted_by  UUID
            );

            -- Roles
            CREATE TABLE IF NOT EXISTS roles (
                id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
                name        VARCHAR(80)  UNIQUE NOT NULL,
                slug        VARCHAR(80)  UNIQUE NOT NULL,
                description TEXT,
                is_system   BOOLEAN      DEFAULT FALSE,
                created_at  TIMESTAMP    DEFAULT NOW(),
                updated_at  TIMESTAMP    DEFAULT NOW(),
                deleted_at  TIMESTAMP,
                created_by  UUID,
                updated_by  UUID,
                deleted_by  UUID
            );

            -- Permissions
            CREATE TABLE IF NOT EXISTS permissions (
                id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
                name        VARCHAR(120) UNIQUE NOT NULL,
                slug        VARCHAR(120) UNIQUE NOT NULL,
                description TEXT,
                module      VARCHAR(80),
                created_at  TIMESTAMP    DEFAULT NOW(),
                updated_at  TIMESTAMP    DEFAULT NOW()
            );

            -- Role ↔ Permission pivot
            CREATE TABLE IF NOT EXISTS role_permissions (
                role_id       UUID NOT NULL,
                permission_id UUID NOT NULL,
                PRIMARY KEY (role_id, permission_id),
                FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
                FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
            );

            -- User ↔ Role pivot
            CREATE TABLE IF NOT EXISTS user_roles (
                user_id UUID NOT NULL,
                role_id UUID NOT NULL,
                PRIMARY KEY (user_id, role_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
            );

            -- Modules
            CREATE TABLE IF NOT EXISTS modules (
                id          UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
                name        VARCHAR(120) UNIQUE NOT NULL,
                slug        VARCHAR(120) UNIQUE NOT NULL,
                version     VARCHAR(20),
                description TEXT,
                author      VARCHAR(120),
                is_core     BOOLEAN      DEFAULT FALSE,
                status      VARCHAR(20)  DEFAULT 'enabled',
                directory   VARCHAR(120) DEFAULT NULL,
                created_at  TIMESTAMP    DEFAULT NOW(),
                updated_at  TIMESTAMP    DEFAULT NOW(),
                deleted_at  TIMESTAMP,
                created_by  UUID,
                updated_by  UUID,
                deleted_by  UUID
            );

            -- Audit Logs
            CREATE TABLE IF NOT EXISTS audit_logs (
                id            UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id       UUID,
                action        VARCHAR(120) NOT NULL,
                resource_type VARCHAR(80),
                resource_id   TEXT,
                details       JSONB,
                ip_address    VARCHAR(45),
                user_agent    TEXT,
                created_at    TIMESTAMP    DEFAULT NOW()
            );
        ");

        // Add FK constraints for audit columns after all tables exist
        $auditFks = [
            // users self-references (bootstrapping: no FK needed, just columns)
            // settings
            "ALTER TABLE settings ADD CONSTRAINT settings_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL",
            "ALTER TABLE settings ADD CONSTRAINT settings_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL",
            "ALTER TABLE settings ADD CONSTRAINT settings_deleted_by_fkey FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL",
            // roles
            "ALTER TABLE roles ADD CONSTRAINT roles_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL",
            "ALTER TABLE roles ADD CONSTRAINT roles_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL",
            "ALTER TABLE roles ADD CONSTRAINT roles_deleted_by_fkey FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL",
            // modules
            "ALTER TABLE modules ADD CONSTRAINT modules_created_by_fkey FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL",
            "ALTER TABLE modules ADD CONSTRAINT modules_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL",
            "ALTER TABLE modules ADD CONSTRAINT modules_deleted_by_fkey FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL",
        ];
        foreach ($auditFks as $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (\PDOException $e) {
                // Constraint already exists - safe to ignore on re-run
            }
        }
    }

    public function seed(): void
    {
        // Core settings
        $settings = [
            ['site_name',       'Vertext CMS',  'string',  'general', 'Site Name'],
            ['site_url',        '',             'string',  'general', 'Site URL'],
            ['site_description','',             'string',  'general', 'Site Description'],
            ['admin_email',     '',             'string',  'general', 'Admin Email'],
            ['default_language','en',           'string',  'general', 'Default Language'],
            ['timezone',        'UTC',          'string',  'general', 'Timezone'],
            ['date_format',     'Y-m-d',        'string',  'general', 'Date Format'],
            ['time_format',     'H:i',          'string',  'general', 'Time Format'],
            ['maintenance_mode','0',            'bool',    'general', 'Maintenance Mode'],
            ['setup_complete',  '1',            'bool',    'system',  'Setup Complete'],
        ];

        $stmt = $this->pdo->prepare(
            "INSERT INTO settings (key, value, type, grp, label) VALUES (?,?,?,?,?)
             ON CONFLICT (key) DO NOTHING"
        );
        foreach ($settings as $s) {
            $stmt->execute($s);
        }

        // Core roles
        $roles = [
            ['Administrator', 'administrator', 'Full system access', 1],
            ['Editor',        'editor',        'Can manage content', 0],
            ['Author',        'author',        'Can create content', 0],
        ];

        $roleStmt = $this->pdo->prepare(
            "INSERT INTO roles (name, slug, description, is_system) VALUES (?,?,?,?)
             ON CONFLICT (slug) DO NOTHING"
        );
        foreach ($roles as $r) {
            $roleStmt->execute($r);
        }

        // Core permissions
        $perms = [
            // Users
            ['users.view',         'users.view',         'View users',             'users'],
            ['users.create',       'users.create',       'Create users',           'users'],
            ['users.update',       'users.update',       'Update users',           'users'],
            ['users.delete',       'users.delete',       'Delete users',           'users'],
            // Roles
            ['roles.view',         'roles.view',         'View roles',             'roles'],
            ['roles.manage',       'roles.manage',       'Manage roles',           'roles'],
            // Permissions
            ['permissions.manage', 'permissions.manage', 'Manage permissions',     'roles'],
            // Modules
            ['modules.view',       'modules.view',       'View modules',           'modules'],
            ['modules.install',    'modules.install',    'Install modules',        'modules'],
            ['modules.uninstall',  'modules.uninstall',  'Uninstall modules',      'modules'],
            ['modules.toggle',     'modules.toggle',     'Enable/disable modules', 'modules'],
            // Settings
            ['settings.view',      'settings.view',      'View settings',          'settings'],
            ['settings.manage',    'settings.manage',    'Manage settings',        'settings'],
            // Content
            ['content.view',       'content.view',       'View content',           'content'],
            ['content.create',     'content.create',     'Create content',         'content'],
            ['content.publish',    'content.publish',    'Publish content',        'content'],
            ['content.delete',     'content.delete',     'Delete content',         'content'],
            // Dashboard
            ['dashboard.view',     'dashboard.view',     'Access dashboard',       'dashboard'],
        ];

        $permStmt = $this->pdo->prepare(
            "INSERT INTO permissions (name, slug, description, module) VALUES (?,?,?,?)
             ON CONFLICT (slug) DO NOTHING"
        );
        foreach ($perms as $p) {
            $permStmt->execute($p);
        }

        // Give Administrator role all permissions
        $this->pdo->exec(
            "INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id
             FROM   roles r, permissions p
             WHERE  r.slug = 'administrator'
             ON CONFLICT DO NOTHING"
        );

        $cmsVersion = \App\CMS\Version::APP;

        // Core modules
        $modules = [
            ['Authentication',   'auth',           $cmsVersion, 'Login, session and password handling', 'Vertext', 1],
            ['Dashboard',        'dashboard',      $cmsVersion, 'Admin dashboard overview',              'Vertext', 1],
            ['Users',            'users',          $cmsVersion, 'User management',                       'Vertext', 1],
            ['Roles',            'roles',          $cmsVersion, 'Role and permission management',        'Vertext', 1],
            ['Module Manager',   'module-manager', $cmsVersion, 'Install and manage modules',            'Vertext', 1],
            ['CMS Settings',     'cms-settings',   $cmsVersion, 'Global CMS configuration',             'Vertext', 1],
            ['Theme Manager',    'theme-manager',  $cmsVersion, 'Front-end theme selection',             'Vertext', 1],
        ];

        $modStmt = $this->pdo->prepare(
            "INSERT INTO modules (name, slug, version, description, author, is_core, status)
             VALUES (?,?,?,?,?,?,?)
             ON CONFLICT (slug) DO NOTHING"
        );
        foreach ($modules as $m) {
            $params = $m;
            $params[] = 'enabled';
            $modStmt->execute($params);
        }
    }

    public function down(): void
    {
        $this->pdo->exec("
            DROP TABLE IF EXISTS audit_logs       CASCADE;
            DROP TABLE IF EXISTS modules          CASCADE;
            DROP TABLE IF EXISTS user_roles       CASCADE;
            DROP TABLE IF EXISTS role_permissions CASCADE;
            DROP TABLE IF EXISTS permissions      CASCADE;
            DROP TABLE IF EXISTS roles            CASCADE;
            DROP TABLE IF EXISTS users            CASCADE;
            DROP TABLE IF EXISTS settings         CASCADE;
        ");
    }
}
