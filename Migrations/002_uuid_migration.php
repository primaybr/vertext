<?php

declare(strict_types=1);

/**
 * Vertext CMS - Migration 002: Convert all primary keys from SERIAL to UUID
 *
 * Converts all tables that used SERIAL/INTEGER primary keys to UUID.
 * Preserves all existing data - foreign key references are re-mapped
 * by joining on the new UUID column before swapping.
 *
 * Run order matters due to FK dependencies:
 *   users, roles, permissions → role_permissions, user_roles
 *   settings, modules, audit_logs (no cross-table FKs)
 *   posts (FK: users) → post_categories, post_tags, blog_comments
 *   post_category_pivot, post_tag_pivot (FK: posts, categories, tags)
 *   media_files (FK: users)
 *   login_attempts (standalone)
 */
class Migration_002_UuidMigration
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        $this->pdo->beginTransaction();

        try {
            // ── 1. Standalone tables (no cross-table FKs) ────────────────────────
            $this->convertStandaloneTable('settings');
            $this->convertStandaloneTable('modules');
            $this->convertStandaloneTable('login_attempts', 'attempted_at');

            // ── 2. Core identity tables ───────────────────────────────────────────
            $this->convertStandaloneTable('permissions');
            $this->convertStandaloneTable('roles');
            $this->convertStandaloneTable('users');

            // ── 3. Pivot tables that reference users/roles/permissions ─────────
            $this->convertPivotTable('user_roles',       ['user_id' => 'users', 'role_id' => 'roles']);
            $this->convertPivotTable('role_permissions', ['role_id' => 'roles', 'permission_id' => 'permissions']);

            // ── 4. Audit logs (user_id FK → users, resource_id stays TEXT) ────────
            $this->convertAuditLogs();

            // ── 5. Blog tables ────────────────────────────────────────────────────
            $this->convertPosts();
            $this->convertStandaloneTable('post_categories');
            $this->convertStandaloneTable('post_tags');
            $this->convertBlogComments();
            $this->convertPivotTable('post_category_pivot', ['post_id' => 'posts', 'category_id' => 'post_categories']);
            $this->convertPivotTable('post_tag_pivot',      ['post_id' => 'posts', 'tag_id' => 'post_tags']);

            // ── 6. Media ──────────────────────────────────────────────────────────
            $this->convertMediaFiles();

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Convert a table that has a SERIAL id column with no outbound FK references to other tables.
     * Adds a UUID column, generates UUIDs, drops old SERIAL id, renames the UUID column.
     *
     * @param string $table
     * @param string $createdAtColumn  Used to detect whether ORM timestamp columns exist.
     */
    private function convertStandaloneTable(string $table, string $createdAtColumn = 'created_at'): void
    {
        // Skip if already UUID
        $type = $this->columnType($table, 'id');
        if ($type === null || str_contains(strtolower($type), 'uuid')) {
            return;
        }

        $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS new_uuid UUID DEFAULT gen_random_uuid()");
        $this->pdo->exec("UPDATE {$table} SET new_uuid = gen_random_uuid() WHERE new_uuid IS NULL");
        $this->pdo->exec("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_pkey");
        $this->pdo->exec("ALTER TABLE {$table} DROP COLUMN id");
        $this->pdo->exec("ALTER TABLE {$table} RENAME COLUMN new_uuid TO id");
        $this->pdo->exec("ALTER TABLE {$table} ADD PRIMARY KEY (id)");
        $this->pdo->exec("ALTER TABLE {$table} ALTER COLUMN id SET DEFAULT gen_random_uuid()");

        // Add updated_at if missing (required by ORM setTimestamps)
        $this->ensureUpdatedAt($table);
    }

    /**
     * Convert a pivot/junction table - no single id column, FK columns are INT, no outbound FKs from id.
     * Drops FK constraints, re-adds UUID FK columns by joining on the new UUID ids.
     *
     * @param string   $table
     * @param array    $fkMap  ['col' => 'referenced_table', ...]
     */
    private function convertPivotTable(string $table, array $fkMap): void
    {
        // Detect if already converted by checking column type of first FK
        $firstCol = array_key_first($fkMap);
        $type = $this->columnType($table, $firstCol);
        if ($type === null || str_contains(strtolower($type), 'uuid')) {
            return;
        }

        // Drop all FK constraints on this table
        $this->dropForeignKeys($table);

        foreach ($fkMap as $col => $refTable) {
            $tmp = "new_{$col}";
            $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$tmp} UUID");
            // Map old integer FK to new UUID via a subquery join
            $this->pdo->exec("
                UPDATE {$table} t
                SET    {$tmp} = r.id
                FROM   (SELECT old_serial.old_int_id, n.id
                        FROM   (SELECT id AS id, ROW_NUMBER() OVER (ORDER BY id) AS old_int_id FROM {$refTable}) n
                        JOIN   (SELECT DISTINCT {$col} AS old_int_id FROM {$table}) old_serial USING (old_int_id)) r
                WHERE  t.{$col} = r.old_int_id
            ");
        }

        // Drop old INT FK columns and rename new UUID columns
        foreach (array_keys($fkMap) as $col) {
            $this->pdo->exec("ALTER TABLE {$table} DROP COLUMN {$col}");
            $this->pdo->exec("ALTER TABLE {$table} RENAME COLUMN new_{$col} TO {$col}");
        }

        // Re-add PK and FK constraints
        $pkCols = implode(', ', array_keys($fkMap));
        $this->pdo->exec("ALTER TABLE {$table} ADD PRIMARY KEY ({$pkCols})");

        foreach ($fkMap as $col => $refTable) {
            $this->pdo->exec("ALTER TABLE {$table} ADD FOREIGN KEY ({$col}) REFERENCES {$refTable}(id) ON DELETE CASCADE");
        }
    }

    private function convertAuditLogs(): void
    {
        $type = $this->columnType('audit_logs', 'id');
        if ($type === null || str_contains(strtolower($type), 'uuid')) {
            return;
        }

        // id column
        $this->pdo->exec("ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS new_uuid UUID DEFAULT gen_random_uuid()");
        $this->pdo->exec("UPDATE audit_logs SET new_uuid = gen_random_uuid() WHERE new_uuid IS NULL");
        $this->pdo->exec("ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_pkey");
        $this->pdo->exec("ALTER TABLE audit_logs DROP COLUMN id");
        $this->pdo->exec("ALTER TABLE audit_logs RENAME COLUMN new_uuid TO id");
        $this->pdo->exec("ALTER TABLE audit_logs ADD PRIMARY KEY (id)");
        $this->pdo->exec("ALTER TABLE audit_logs ALTER COLUMN id SET DEFAULT gen_random_uuid()");

        // user_id FK → users
        $userIdType = $this->columnType('audit_logs', 'user_id');
        if ($userIdType && !str_contains(strtolower($userIdType), 'uuid')) {
            $this->dropForeignKeys('audit_logs');
            $this->pdo->exec("ALTER TABLE audit_logs ADD COLUMN new_user_id UUID");
            $this->pdo->exec("
                UPDATE audit_logs al
                SET    new_user_id = u.id
                FROM   (SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS old_int FROM users) u
                WHERE  al.user_id = u.old_int
            ");
            $this->pdo->exec("ALTER TABLE audit_logs DROP COLUMN user_id");
            $this->pdo->exec("ALTER TABLE audit_logs RENAME COLUMN new_user_id TO user_id");
        }

        // resource_id: change from INT to TEXT if needed
        $ridType = $this->columnType('audit_logs', 'resource_id');
        if ($ridType && !str_contains(strtolower($ridType), 'text') && !str_contains(strtolower($ridType), 'char')) {
            $this->pdo->exec("ALTER TABLE audit_logs ALTER COLUMN resource_id TYPE TEXT USING resource_id::TEXT");
        }
    }

    private function convertPosts(): void
    {
        $type = $this->columnType('posts', 'id');
        if ($type === null || str_contains(strtolower($type), 'uuid')) {
            return;
        }

        // Drop FKs pointing FROM other tables TO posts.id first
        $this->dropForeignKeysReferencingTable('blog_comments', 'posts');
        $this->dropForeignKeysReferencingTable('post_category_pivot', 'posts');
        $this->dropForeignKeysReferencingTable('post_tag_pivot', 'posts');

        // Convert posts.id
        $this->pdo->exec("ALTER TABLE posts ADD COLUMN new_uuid UUID DEFAULT gen_random_uuid()");
        $this->pdo->exec("UPDATE posts SET new_uuid = gen_random_uuid() WHERE new_uuid IS NULL");
        $this->pdo->exec("ALTER TABLE posts DROP CONSTRAINT IF EXISTS posts_pkey");
        $this->pdo->exec("ALTER TABLE posts DROP COLUMN id");
        $this->pdo->exec("ALTER TABLE posts RENAME COLUMN new_uuid TO id");
        $this->pdo->exec("ALTER TABLE posts ADD PRIMARY KEY (id)");
        $this->pdo->exec("ALTER TABLE posts ALTER COLUMN id SET DEFAULT gen_random_uuid()");

        // Convert posts.author_id (INT → UUID, FK → users)
        $authorType = $this->columnType('posts', 'author_id');
        if ($authorType && !str_contains(strtolower($authorType), 'uuid')) {
            $this->pdo->exec("ALTER TABLE posts ADD COLUMN new_author_id UUID");
            $this->pdo->exec("
                UPDATE posts p
                SET    new_author_id = u.id
                FROM   (SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS old_int FROM users) u
                WHERE  p.author_id = u.old_int
            ");
            $this->pdo->exec("ALTER TABLE posts DROP COLUMN author_id");
            $this->pdo->exec("ALTER TABLE posts RENAME COLUMN new_author_id TO author_id");
            $this->pdo->exec("ALTER TABLE posts ADD FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL");
        }

        // Convert posts.featured_image_id (INT → UUID, no FK enforced - media module may not be installed)
        $imgType = $this->columnType('posts', 'featured_image_id');
        if ($imgType && str_contains(strtolower($imgType), 'int')) {
            $this->pdo->exec("ALTER TABLE posts ALTER COLUMN featured_image_id TYPE UUID USING NULL");
        }

        $this->ensureUpdatedAt('posts');
    }

    private function convertBlogComments(): void
    {
        $type = $this->columnType('blog_comments', 'id');
        if ($type === null || str_contains(strtolower($type), 'uuid')) {
            return;
        }

        $this->dropForeignKeys('blog_comments');

        // id
        $this->pdo->exec("ALTER TABLE blog_comments ADD COLUMN new_uuid UUID DEFAULT gen_random_uuid()");
        $this->pdo->exec("UPDATE blog_comments SET new_uuid = gen_random_uuid() WHERE new_uuid IS NULL");
        $this->pdo->exec("ALTER TABLE blog_comments DROP CONSTRAINT IF EXISTS blog_comments_pkey");
        $this->pdo->exec("ALTER TABLE blog_comments DROP COLUMN id");
        $this->pdo->exec("ALTER TABLE blog_comments RENAME COLUMN new_uuid TO id");
        $this->pdo->exec("ALTER TABLE blog_comments ADD PRIMARY KEY (id)");
        $this->pdo->exec("ALTER TABLE blog_comments ALTER COLUMN id SET DEFAULT gen_random_uuid()");

        // post_id FK → posts
        $postIdType = $this->columnType('blog_comments', 'post_id');
        if ($postIdType && !str_contains(strtolower($postIdType), 'uuid')) {
            $this->pdo->exec("ALTER TABLE blog_comments ADD COLUMN new_post_id UUID");
            $this->pdo->exec("
                UPDATE blog_comments c
                SET    new_post_id = p.id
                FROM   (SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS old_int FROM posts) p
                WHERE  c.post_id = p.old_int
            ");
            $this->pdo->exec("ALTER TABLE blog_comments DROP COLUMN post_id");
            $this->pdo->exec("ALTER TABLE blog_comments RENAME COLUMN new_post_id TO post_id");
            $this->pdo->exec("ALTER TABLE blog_comments ALTER COLUMN post_id SET NOT NULL");
            $this->pdo->exec("ALTER TABLE blog_comments ADD FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE");
        }

        $this->ensureUpdatedAt('blog_comments');
    }

    private function convertMediaFiles(): void
    {
        $type = $this->columnType('media_files', 'id');
        if ($type === null || str_contains(strtolower($type), 'uuid')) {
            return;
        }

        $this->dropForeignKeys('media_files');

        // id
        $this->pdo->exec("ALTER TABLE media_files ADD COLUMN new_uuid UUID DEFAULT gen_random_uuid()");
        $this->pdo->exec("UPDATE media_files SET new_uuid = gen_random_uuid() WHERE new_uuid IS NULL");
        $this->pdo->exec("ALTER TABLE media_files DROP CONSTRAINT IF EXISTS media_files_pkey");
        $this->pdo->exec("ALTER TABLE media_files DROP COLUMN id");
        $this->pdo->exec("ALTER TABLE media_files RENAME COLUMN new_uuid TO id");
        $this->pdo->exec("ALTER TABLE media_files ADD PRIMARY KEY (id)");
        $this->pdo->exec("ALTER TABLE media_files ALTER COLUMN id SET DEFAULT gen_random_uuid()");

        // uploaded_by FK → users
        $uploadedByType = $this->columnType('media_files', 'uploaded_by');
        if ($uploadedByType && !str_contains(strtolower($uploadedByType), 'uuid')) {
            $this->pdo->exec("ALTER TABLE media_files ADD COLUMN new_uploaded_by UUID");
            $this->pdo->exec("
                UPDATE media_files m
                SET    new_uploaded_by = u.id
                FROM   (SELECT id, ROW_NUMBER() OVER (ORDER BY id) AS old_int FROM users) u
                WHERE  m.uploaded_by = u.old_int
            ");
            $this->pdo->exec("ALTER TABLE media_files DROP COLUMN uploaded_by");
            $this->pdo->exec("ALTER TABLE media_files RENAME COLUMN new_uploaded_by TO uploaded_by");
            $this->pdo->exec("ALTER TABLE media_files ADD FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL");
        }

        $this->ensureUpdatedAt('media_files');
    }

    // ── Internal utilities ────────────────────────────────────────────────────

    private function columnType(string $table, string $column): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT data_type
            FROM   information_schema.columns
            WHERE  table_name = :t AND column_name = :c AND table_schema = 'public'
        ");
        $stmt->execute([':t' => $table, ':c' => $column]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? $row['data_type'] : null;
    }

    private function ensureUpdatedAt(string $table): void
    {
        $type = $this->columnType($table, 'updated_at');
        if ($type === null) {
            $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN updated_at TIMESTAMP DEFAULT NOW()");
        }
    }

    private function dropForeignKeys(string $table): void
    {
        $stmt = $this->pdo->prepare("
            SELECT constraint_name
            FROM   information_schema.table_constraints
            WHERE  table_name = :t AND constraint_type = 'FOREIGN KEY' AND table_schema = 'public'
        ");
        $stmt->execute([':t' => $table]);
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $name) {
            $this->pdo->exec("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS \"{$name}\"");
        }
    }

    private function dropForeignKeysReferencingTable(string $fromTable, string $toTable): void
    {
        $stmt = $this->pdo->prepare("
            SELECT tc.constraint_name
            FROM   information_schema.table_constraints tc
            JOIN   information_schema.referential_constraints rc
                   ON rc.constraint_name = tc.constraint_name
            JOIN   information_schema.table_constraints tc2
                   ON tc2.constraint_name = rc.unique_constraint_name
            WHERE  tc.table_name = :from AND tc2.table_name = :to AND tc.table_schema = 'public'
        ");
        $stmt->execute([':from' => $fromTable, ':to' => $toTable]);
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $name) {
            $this->pdo->exec("ALTER TABLE {$fromTable} DROP CONSTRAINT IF EXISTS \"{$name}\"");
        }
    }

    public function down(): void
    {
        // UUID→SERIAL downgrade is destructive (data loss) - not supported.
        throw new \RuntimeException('Migration 002 cannot be rolled back. Restore from backup if needed.');
    }
}
