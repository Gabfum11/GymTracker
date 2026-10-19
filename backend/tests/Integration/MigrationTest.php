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
            ->fetchAll(\PDO::FETCH_COLUMN); //chiede al db tutta la lista delle sue tabelle

        foreach (['exercises', 'otp_codes', 'rate_limits', 'refresh_tokens', 'schema_migrations', 'sets', 'users', 'workouts'] as $table) {
            $this->assertContains($table, $tables); //verifica con le tabelle attese
        }
    }

    public function test_only_one_open_workout_per_user(): void
    {
        $userId = $this->createUser();
        $this->pdo->prepare('INSERT INTO workouts (user_id) VALUES (?)')->execute([$userId]);

        try { //prova ad avviare un secondo allenamento senza chiudere il primo, il db deve rifiutarlo
            $this->pdo->prepare('INSERT INTO workouts (user_id) VALUES (?)')->execute([$userId]);
            $this->fail('Un secondo allenamento aperto doveva essere rifiutato'); //se il secondo inserimento passa vuol dire che la regola non funziona
        } catch (PDOException $e) {
            //controlla che l'errore sia proprio il codice 23505 cioè una regola che vieta i duplicati
            $this->assertSame('23505', $e->getCode()); // 23505 = violazione di un vincolo UNIQUE
        }
    }

    public function test_migrations_are_applied_only_once(): void
    {
        $applied = \App\Migrator::run($this->pdo, __DIR__ . '/../../migrations');
        //verifica che se il migrator viene lanciato di nuovo non applichi niente, cioè restituisca una lista vuota
        $this->assertSame([], $applied); 
    }
}
