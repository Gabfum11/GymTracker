<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Tutto l'SQL della tabella workouts. */
final class WorkoutRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** L'allenamento in corso (senza ended_at), se c'è. */
    public function findOpen(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM workouts WHERE user_id = ? AND ended_at IS NULL');
        $stmt->execute([$userId]);

        return $stmt->fetch() ?: null;
    }

    public function find(int $userId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM workouts WHERE user_id = ? AND id = ?');
        $stmt->execute([$userId, $id]);

        return $stmt->fetch() ?: null;
    }

    public function create(int $userId): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO workouts (user_id) VALUES (?) RETURNING *');
        $stmt->execute([$userId]);

        return $stmt->fetch();
    }

    public function finish(int $id): void
    {
        $this->pdo->prepare('UPDATE workouts SET ended_at = now() WHERE id = ? AND ended_at IS NULL')->execute([$id]);
    }
}
