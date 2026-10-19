<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Tutto l'SQL della tabella refresh_tokens. Riceve e cerca solo hash, mai token in chiaro. */
final class RefreshTokenRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $userId, string $tokenHash, int $days): void
    {
        $this->pdo->prepare("INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (?, ?, now() + make_interval(days => ?))")
            ->execute([$userId, $tokenHash, $days]);
    }

    public function findByHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare('SELECT *, expires_at <= now() AS expired FROM refresh_tokens WHERE token_hash = ?');
        $stmt->execute([$tokenHash]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Segna il token come usato. Restituisce false se era già stato usato:
     * la condizione nella stessa query evita che due richieste simultanee lo usino entrambe.
     */
    public function markUsed(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE refresh_tokens SET used_at = now() WHERE id = ? AND used_at IS NULL');
        $stmt->execute([$id]);

        return $stmt->rowCount() === 1;
    }

    public function delete(string $tokenHash): void
    {
        $this->pdo->prepare('DELETE FROM refresh_tokens WHERE token_hash = ?')->execute([$tokenHash]);
    }

    public function deleteForUser(int $userId): void
    {
        $this->pdo->prepare('DELETE FROM refresh_tokens WHERE user_id = ?')->execute([$userId]);
    }
}
