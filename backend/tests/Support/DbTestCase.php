<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database;
use App\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base per i test che usano il database: prima di ogni test applica le migrazioni
 * e svuota tutte le tabelle, così ogni test parte da zero.
 */
abstract class DbTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void //gira automaticamente prima di ciascun test
    {
        $this->pdo = Database::connect($_ENV); //si collega al db
        Migrator::run($this->pdo, __DIR__ . '/../../migrations'); //esegue le migrazioni con il migrator
        $this->pdo->exec('TRUNCATE users, auth_tokens, exercises, workouts, sets RESTART IDENTITY CASCADE');
        //svuota tutte le tabelle con truncate, cosi ogni test parte da un db pulito e non risente dei dati lasciati da un altro
    }

    protected function createUser(string $email = 'a@test.it'): int
    //inserisce un utente di prova e restituisce il suo id
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?) RETURNING id');
        $stmt->execute(['Test', $email, 'x']);

        return (int) $stmt->fetchColumn();
    }
}
