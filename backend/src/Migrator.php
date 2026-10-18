<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Applica i file .sql della cartella migrations che il database non ha ancora visto.
 * Quelli già applicati sono annotati nella tabella schema_migrations.
 */
final class Migrator
{
    /** @return list<string> i nomi dei file applicati in questa esecuzione */
    public static function run(PDO $pdo, string $dir): array
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            name TEXT PRIMARY KEY,
            applied_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )');
        $done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

        $files = glob($dir . '/*.sql');
        sort($files); // 001_, 002_, ... in ordine

        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            // tutto o niente: se una riga del file fallisce, il database resta com'era
            $pdo->beginTransaction();
            $pdo->exec(file_get_contents($file));
            $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]);
            $pdo->commit();
            $applied[] = $name;
        }

        return $applied;
    }
}
