<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Tutto l'SQL della tabella exercises. Ogni query filtra per utente: nessuno vede gli esercizi degli altri. */
final class ExerciseRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, is_weighted_bodyweight FROM exercises WHERE user_id = ? ORDER BY lower(name)');
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function find(int $userId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, is_weighted_bodyweight FROM exercises WHERE user_id = ? AND id = ?');
        $stmt->execute([$userId, $id]);

        return $stmt->fetch() ?: null; // fetch() restituisce false se non trova niente
    }

    /** @return array{exercise: array, created: bool} */
    public function findOrCreate(int $userId, string $name, bool $weighted): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, is_weighted_bodyweight FROM exercises WHERE user_id = ? AND lower(name) = lower(?)');
        $stmt->execute([$userId, $name]);
        $existing = $stmt->fetch();
        if ($existing) {
            return ['exercise' => $existing, 'created' => false];
        }

        $stmt = $this->pdo->prepare('INSERT INTO exercises (user_id, name, is_weighted_bodyweight) VALUES (?, ?, ?)
                                     RETURNING id, name, is_weighted_bodyweight');
        $stmt->execute([$userId, $name, $weighted ? 'true' : 'false']);

        return ['exercise' => $stmt->fetch(), 'created' => true];
    }
}
