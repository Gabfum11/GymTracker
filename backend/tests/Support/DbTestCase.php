<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database;
use App\Migrator;
use App\Service\TokenService;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

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
        // solo nei test: non aspettare la scrittura fisica su disco a ogni salvataggio (in Docker su Windows costa ~0,2 s ciascuno)
        $this->pdo->exec('SET synchronous_commit TO off');
        Migrator::run($this->pdo, __DIR__ . '/../../migrations'); //esegue le migrazioni con il migrator
        // DELETE invece di TRUNCATE: su tabelle quasi vuote è istantaneo, TRUNCATE in Docker su Windows impiega secondi
        $this->pdo->exec('DELETE FROM sets; DELETE FROM workouts; DELETE FROM exercises; DELETE FROM otp_codes;
                          DELETE FROM refresh_tokens; DELETE FROM rate_limits; DELETE FROM users;');
        // gli id NON ripartono da 1 (azzerarli costa ~0,5 s per test): i test usano gli id veri
        //svuota tutte le tabelle con truncate, cosi ogni test parte da un db pulito e non risente dei dati lasciati da un altro
    }

    protected function tearDown(): void
    {
        unset($this->pdo); // chiude la connessione: PHPUnit tiene in memoria i test finiti, che altrimenti la terrebbero aperta
    }

    protected function createUser(string $email = 'a@test.it'): int
    //inserisce un utente di prova e restituisce il suo id
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?) RETURNING id');
        $stmt->execute(['Test', $email, 'x']);

        return (int) $stmt->fetchColumn();
    }

    /** Segreto per firmare i JWT nei test (in produzione arriva da JWT_SECRET). */
    protected const JWT_SECRET = 'segreto-dei-test-lungo-almeno-32-caratteri!!';

    /** Un access token valido per l'utente, senza passare dal login. */
    protected function tokenFor(int $userId): string
    {
        return (new TokenService(self::JWT_SECRET))->accessToken($userId, 0);
    }

    /** Manda una richiesta all'app e restituisce [codice HTTP, corpo JSON già decodificato]. */
    protected function request(App $app, string $method, string $path, ?array $body = null, ?string $token = null, string $ip = '10.0.0.1'): array
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path, ['REMOTE_ADDR' => $ip]);
        if ($token !== null) {
            $request = $request->withHeader('Authorization', "Bearer $token");
        }
        if ($body !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request->getBody()->write(json_encode($body));
        }
        $response = $app->handle($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }
}
