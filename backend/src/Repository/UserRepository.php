<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Tutto l'SQL della tabella users. Le email arrivano già in minuscolo (AuthService). */
final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);

        return $stmt->fetch() ?: null;
    }

    public function create(string $name, string $email, string $passwordHash): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?) RETURNING *');
        $stmt->execute([$name, $email, $passwordHash]);

        return $stmt->fetch();
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    public function markVerified(int $id): void
    {
        $this->pdo->prepare('UPDATE users SET is_verified = true WHERE id = ?')->execute([$id]);
    }

    /** Invalida tutti gli access token già emessi per l'utente. */
    public function bumpTokenVersion(int $id): void
    {
        $this->pdo->prepare('UPDATE users SET token_version = token_version + 1 WHERE id = ?')->execute([$id]);
    }

    public function setPassword(int $id, string $passwordHash): void
    {
        $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$passwordHash, $id]);
    }
}
