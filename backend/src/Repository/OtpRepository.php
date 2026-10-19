<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Tutto l'SQL della tabella otp_codes ("verify" = verifica email, "reset" = recupero password). */
final class OtpRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Salva un codice nuovo, sostituendo quello vecchio dello stesso tipo. */
    public function replace(int $userId, string $purpose, string $code, int $minutes): void
    {
        $this->pdo->prepare(
            "INSERT INTO otp_codes (user_id, purpose, code, expires_at) VALUES (?, ?, ?, now() + make_interval(mins => ?))
             ON CONFLICT (user_id, purpose) DO UPDATE SET code = EXCLUDED.code, attempts = 0, expires_at = EXCLUDED.expires_at"
        )->execute([$userId, $purpose, $code, $minutes]);
    }

    /** Il codice ancora valido (non scaduto), se c'è. */
    public function findValid(int $userId, string $purpose): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM otp_codes WHERE user_id = ? AND purpose = ? AND expires_at > now()');
        $stmt->execute([$userId, $purpose]);

        return $stmt->fetch() ?: null;
    }

    public function addAttempt(int $id): int
    {
        $stmt = $this->pdo->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ? RETURNING attempts');
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM otp_codes WHERE id = ?')->execute([$id]);
    }
}
