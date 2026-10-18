<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDOException;
use Tests\Support\DbTestCase;

final class MigrationTest extends DbTestCase
{
    public function test_all_tables_exist(): void
    {
        $tables = $this->pdo
            ->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name")
            ->fetchAll(\PDO::FETCH_COLUMN);

        foreach (['auth_tokens', 'exercises', 'schema_migrations', 'sets', 'users', 'workouts'] as $table) {
            $this->assertContains($table, $tables);
        }
    }

    public function test_only_one_open_workout_per_user(): void
    {
        $userId = $this->createUser();
        $this->pdo->prepare('INSERT INTO workouts (user_id) VALUES (?)')->execute([$userId]);

        try {
            $this->pdo->prepare('INSERT INTO workouts (user_id) VALUES (?)')->execute([$userId]);
            $this->fail('Un secondo allenamento aperto doveva essere rifiutato');
        } catch (PDOException $e) {
            $this->assertSame('23505', $e->getCode()); // 23505 = violazione di un vincolo UNIQUE
        }
    }

    public function test_migrations_are_applied_only_once(): void
    {
        $applied = \App\Migrator::run($this->pdo, __DIR__ . '/../../migrations');

        $this->assertSame([], $applied);
    }
}
