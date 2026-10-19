<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

/**
 * Conta i tentativi per "secchio" (es. "login:10.0.0.1") in una finestra di tempo.
 * Il contatore vive nel database, così vale anche se il server si riavvia.
 */
final class RateLimiter
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Registra un tentativo; false se il limite è superato. */
    public function hit(string $bucket, int $limit, int $windowSeconds): bool
    {
        // una sola query atomica: se la finestra è scaduta riparte da 1, altrimenti aggiunge 1
        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limits (bucket, count, reset_at) VALUES (?, 1, now() + make_interval(secs => ?))
             ON CONFLICT (bucket) DO UPDATE SET
                 count    = CASE WHEN rate_limits.reset_at <= now() THEN 1 ELSE rate_limits.count + 1 END,
                 reset_at = CASE WHEN rate_limits.reset_at <= now() THEN EXCLUDED.reset_at ELSE rate_limits.reset_at END
             RETURNING count'
        );
        $stmt->execute([$bucket, $windowSeconds]);

        return (int) $stmt->fetchColumn() <= $limit;
    }

    /** Come hit(), ma oltre il limite lancia l'eccezione (→ 429). */
    public function check(string $bucket, int $limit, int $windowSeconds, string $message): void
    {
        if (!$this->hit($bucket, $limit, $windowSeconds)) {
            throw new TooManyRequestsException($message);
        }
    }
}
