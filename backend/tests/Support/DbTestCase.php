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

    protected function setUp(): void
    {
        $this->pdo = Database::connect($_ENV);
        Migrator::run($this->pdo, __DIR__ . '/../../migrations');
        $this->pdo->exec('TRUNCATE users, auth_tokens, exercises, workouts, sets RESTART IDENTITY CASCADE');
    }

    protected function createUser(string $email = 'a@test.it'): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?) RETURNING id');
        $stmt->execute(['Test', $email, 'x']);

        return (int) $stmt->fetchColumn();
    }
}
