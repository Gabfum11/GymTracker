<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * il db parte vuoto. Le tabelle sono descritte in .sql numerati
 * Una migrazione è uno di questi file: un passo di modifica dello schema del db.
 * Lo schema è la struttura del db, cioè quali tabelle e quali colonne ci sono
 * Migrator.php esegue i file che il db non ha ancora applicato, uno dopo l'altro e in ordine
 * lanciato più volte, si ricorda quali file ha già eseguto
*/
final class Migrator
{
    /** @return list<string> i nomi dei file applicati in questa esecuzione */
    public static function run(PDO $pdo, string $dir): array //restituisce un array con i nomi dei file che ha applicato
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            name TEXT PRIMARY KEY,
            applied_at TIMESTAMPTZ NOT NULL DEFAULT now()
        )');
        $done = $pdo->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

        $files = glob($dir . '/*.sql'); //glob trova tutti i file .sql nella cartella 
        sort($files); // in questo modo sono ordinati :001_, 002_, ... 

        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);  //basename prende solo il nome del file, senza il percorso
            if (in_array($name, $done, true)) {
                continue; //se il nome è già nel registro, continue lo salta e passa al file successivo. Il true in in_array chede un confronto esatto
            }
            // tutto o niente: se una riga del file fallisce, il database resta com'era
            $pdo->beginTransaction(); //da qui in poi le modifiche diventano definitive solo alla fine
            $pdo->exec(file_get_contents($file)); //legge il file e manda al db l'sql che contiene
            $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]);
            $pdo->commit(); //rende tutto definitivo
            $applied[] = $name;
        }

        return $applied;
    }
}
