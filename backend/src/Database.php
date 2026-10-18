<?php
//apre la connessione al database PostgreSQL e la restituisce a chi la chiede
declare(strict_types=1);

namespace App;

use PDO; //strumento per parlare con i db. use permette di scrivere solo PDO invece del nomespace completo \PDO

/** Apre la connessione a PostgreSQL leggendo i dati di accesso dalle variabili d'ambiente. */
final class Database
{
    public static function connect(array $env): PDO //promette di restituire un oggetto PDO, che rappresenta la connessione al database
    {
        //sprintf costruisce la stringa: ogni %s viene sostituito con il valore corrispondente. Se manca una variabile d'ambiente, viene usato un valore di default.
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            $env['DB_HOST'],
            $env['DB_PORT'] ?? '5432', //se manca usa la porta standard di PostgreSQL
            $env['DB_NAME'],
            $env['DB_SSLMODE'] ?? 'prefer', //sslmode decidese la connessione è cifrata. prefer la cifra se il server lo permette. Su supabase servirà require, cioè cifratura obbligatoria
        );

        return new PDO($dsn, $env['DB_USER'], $env['DB_PASSWORD'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,       // un errore SQL diventa un'eccezione, non un false silenzioso
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // le righe arrivano come array con il nome della colonna come chiave ['colonna' => valore]
        ]);
    }
}
