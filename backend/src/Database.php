<?php

declare(strict_types=1);

namespace App;

use PDO;

/** Apre la connessione a PostgreSQL leggendo i dati di accesso dalle variabili d'ambiente. */
final class Database
{
    public static function connect(array $env): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            $env['DB_HOST'],
            $env['DB_PORT'] ?? '5432',
            $env['DB_NAME'],
            $env['DB_SSLMODE'] ?? 'prefer', // su Supabase sarà "require"
        );

        return new PDO($dsn, $env['DB_USER'], $env['DB_PASSWORD'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,       // un errore SQL diventa un'eccezione, non un false silenzioso
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // le righe arrivano come array ['colonna' => valore]
        ]);
    }
}
