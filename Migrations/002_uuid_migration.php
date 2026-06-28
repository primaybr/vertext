<?php

declare(strict_types=1);

/**
 * Migration 002: Convert SERIAL/INTEGER primary keys to UUID
 *
 * Uses a shadow-column strategy so FK mappings are done while old INT ids
 * still exist - avoiding the ROW_NUMBER() gap bug and FK ordering deadlocks:
 *
 *  Phase 1 - Discover all tables with INT id column
 *  Phase 2 - Add _uuid shadow column to every parent table; populate immediately
 *  Phase 3 - Add _uuid_<col> shadow columns to FK columns in child tables;
 *             map via JOIN on old INT id while it still exists
 *  Phase 4 - Drop ALL FK constraints (unblocks column drops/renames)
 *  Phase 5 - Swap id columns: DROP id, RENAME _uuid TO id, ADD PRIMARY KEY
 *  Phase 6 - Swap FK columns: DROP old INT col, RENAME _uuid_<col> TO <col>
 *  Phase 7 - Re-add composite PKs for known pivot tables
 *  Phase 8 - Fix audit_logs.resource_id type INT -> TEXT
 *  Phase 9 - Ensure updated_at exists on all converted tables
 *
 * Safe to run on a DB already using UUIDs - Phase 1 returns empty list and
 * the migration exits as a no-op without touching anything.
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
            // ── Phase 1: Discover tables with INT id ───────────────────────────
            $idTables = $this->tablesWithIntId();

            if (empty($idTables)) {
                $this->pdo->commit();
                return; // Already UUID - nothing to do
            }

            // ── Phase 2: Add _uuid shadow to every parent table ────────────────
            // Populate NOW while old INT id still exists (needed for Phase 3 JOIN)
            foreach ($idTables as $t) {
                $this->pdo->exec("ALTER TABLE \"{$t}\" ADD COLUMN IF NOT EXISTS _uuid UUID DEFAULT gen_random_uuid()");
                $this->pdo->exec("UPDATE \"{$t}\" SET _uuid = gen_random_uuid() WHERE _uuid IS NULL");
            }

            // ── Phase 3: Map FK columns in child tables via old INT id ─────────
            // CRITICAL: child.fk_col = parent.id (INT) - parent.id still exists here.
            // For NULL FK values (nullable FKs) the JOIN produces no match and the
            // shadow column stays NULL, which is the correct outcome.
            $fks = $this->intFkColumns($idTables);
            foreach ($fks as ['table' => $t, 'col' => $c, 'ref' => $r]) {
                $tmp = "_uuid_{$c}";
                $this->pdo->exec("ALTER TABLE \"{$t}\" ADD COLUMN IF NOT EXISTS \"{$tmp}\" UUID");
                $this->pdo->exec(
                    "UPDATE \"{$t}\" x
                     SET    \"{$tmp}\" = p._uuid
                     FROM   \"{$r}\" p
                     WHERE  x.\"{$c}\" = p.id"
                );
            }

            // ── Phase 4: Drop ALL FK constraints ──────────────────────────────
            // Must happen before we drop/rename columns that FK constraints reference.
            foreach ($this->allFkConstraints() as ['table' => $t, 'name' => $n]) {
                $this->pdo->exec("ALTER TABLE \"{$t}\" DROP CONSTRAINT IF EXISTS \"{$n}\"");
            }

            // ── Phase 5: Swap id columns on parent tables ─────────────────────
            foreach ($idTables as $t) {
                $pk = $this->pkConstraintName($t);
                if ($pk) {
                    $this->pdo->exec("ALTER TABLE \"{$t}\" DROP CONSTRAINT \"{$pk}\"");
                }
                $this->pdo->exec("ALTER TABLE \"{$t}\" DROP COLUMN id");
                $this->pdo->exec("ALTER TABLE \"{$t}\" RENAME COLUMN _uuid TO id");
                $this->pdo->exec("ALTER TABLE \"{$t}\" ADD PRIMARY KEY (id)");
                $this->pdo->exec("ALTER TABLE \"{$t}\" ALTER COLUMN id SET DEFAULT gen_random_uuid()");
            }

            // ── Phase 6: Swap FK columns in child tables ───────────────────────
            foreach ($fks as ['table' => $t, 'col' => $c]) {
                $tmp = "_uuid_{$c}";
                $this->pdo->exec("ALTER TABLE \"{$t}\" DROP COLUMN \"{$c}\"");
                $this->pdo->exec("ALTER TABLE \"{$t}\" RENAME COLUMN \"{$tmp}\" TO \"{$c}\"");
            }

            // ── Phase 7: Re-add composite PKs for known pivot tables ──────────
            foreach ($this->compositePivots() as $tbl => $cols) {
                if (!$this->tableExists($tbl)) {
                    continue;
                }
                if ($this->pkConstraintName($tbl)) {
                    continue; // Already has PK
                }
                $this->pdo->exec("ALTER TABLE \"{$tbl}\" ADD PRIMARY KEY (" . implode(', ', $cols) . ")");
            }

            // ── Phase 8: Fix audit_logs.resource_id INT -> TEXT ────────────────
            if ($this->tableExists('audit_logs')) {
                $rt = $this->colType('audit_logs', 'resource_id');
                if ($rt !== null && in_array($rt, ['integer', 'bigint', 'smallint'], true)) {
                    $this->pdo->exec("ALTER TABLE audit_logs ALTER COLUMN resource_id TYPE TEXT USING resource_id::TEXT");
                }
            }

            // ── Phase 9: Ensure updated_at on all converted tables ─────────────
            foreach ($idTables as $t) {
                if ($this->colType($t, 'updated_at') === null) {
                    $this->pdo->exec("ALTER TABLE \"{$t}\" ADD COLUMN updated_at TIMESTAMP DEFAULT NOW()");
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Returns table names in public schema whose id column is an integer type. */
    private function tablesWithIntId(): array
    {
        $stmt = $this->pdo->query("
            SELECT table_name
            FROM   information_schema.columns
            WHERE  table_schema = 'public'
            AND    column_name  = 'id'
            AND    data_type   IN ('integer', 'bigint', 'smallint')
            ORDER  BY table_name
        ");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * FK columns that:
     *  - point to one of the $idTables (we're converting those tables)
     *  - are themselves integer-typed (not already UUID)
     *
     * Returns array of ['table' => ..., 'col' => ..., 'ref' => ...]
     */
    private function intFkColumns(array $idTables): array
    {
        if (empty($idTables)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($idTables), '?'));
        $stmt = $this->pdo->prepare("
            SELECT
                kcu.table_name  AS \"table\",
                kcu.column_name AS col,
                ccu.table_name  AS ref
            FROM   information_schema.table_constraints      tc
            JOIN   information_schema.key_column_usage       kcu
                   ON  kcu.constraint_name = tc.constraint_name
                   AND kcu.table_schema    = tc.table_schema
            JOIN   information_schema.constraint_column_usage ccu
                   ON  ccu.constraint_name = tc.constraint_name
                   AND ccu.table_schema    = tc.table_schema
            JOIN   information_schema.columns                c
                   ON  c.table_name   = kcu.table_name
                   AND c.column_name  = kcu.column_name
                   AND c.table_schema = tc.table_schema
            WHERE  tc.constraint_type = 'FOREIGN KEY'
            AND    tc.table_schema    = 'public'
            AND    ccu.table_name    IN ({$placeholders})
            AND    c.data_type       IN ('integer', 'bigint', 'smallint')
        ");
        $stmt->execute($idTables);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** All FK constraint names across the public schema. */
    private function allFkConstraints(): array
    {
        $stmt = $this->pdo->query("
            SELECT table_name AS \"table\", constraint_name AS name
            FROM   information_schema.table_constraints
            WHERE  constraint_type = 'FOREIGN KEY'
            AND    table_schema    = 'public'
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Returns the PK constraint name for a table, or null if none found. */
    private function pkConstraintName(string $table): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT constraint_name
            FROM   information_schema.table_constraints
            WHERE  table_name      = ?
            AND    constraint_type = 'PRIMARY KEY'
            AND    table_schema    = 'public'
            LIMIT  1
        ");
        $stmt->execute([$table]);
        return $stmt->fetchColumn() ?: null;
    }

    /** Known pivot tables with composite PKs (no separate id column). */
    private function compositePivots(): array
    {
        return [
            'user_roles'          => ['user_id', 'role_id'],
            'role_permissions'    => ['role_id', 'permission_id'],
            'post_category_pivot' => ['post_id', 'category_id'],
            'post_tag_pivot'      => ['post_id', 'tag_id'],
        ];
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM information_schema.tables
            WHERE  table_schema = 'public' AND table_name = ?
        ");
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    /** Returns the data_type of a column, or null if the column does not exist. */
    private function colType(string $table, string $column): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT data_type
            FROM   information_schema.columns
            WHERE  table_schema = 'public' AND table_name = ? AND column_name = ?
        ");
        $stmt->execute([$table, $column]);
        return $stmt->fetchColumn() ?: null;
    }

    public function down(): void
    {
        throw new \RuntimeException('Migration 002 cannot be rolled back. Restore from backup if needed.');
    }
}
