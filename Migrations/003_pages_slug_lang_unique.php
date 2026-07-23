<?php

declare(strict_types=1);

/**
 * pages.slug was globally UNIQUE, so the same logical page (e.g. "about")
 * couldn't have an id and en row side by side - a bilingual rollout needs
 * exactly that. Replaces the single-column unique constraint with one
 * scoped to (slug, lang).
 */
class Migration_003_PagesSlugLangUnique
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        if (!$this->tableExists('pages')) {
            return;
        }

        $this->pdo->exec("ALTER TABLE pages DROP CONSTRAINT IF EXISTS pages_slug_key");
        $this->pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS pages_slug_lang_uidx ON pages (slug, lang)");
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :table"
        );
        $stmt->execute([':table' => $table]);
        return (bool) $stmt->fetchColumn();
    }
}
