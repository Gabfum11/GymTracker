<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

/** Tutto l'SQL della tabella sets. */
final class SetRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Sostituisce tutte le serie di un esercizio in un allenamento (cancella e reinserisce).
     * @param list<array{weight_kg: float, reps: int}> $sets serie già validate; lista vuota = toglie l'esercizio
     */
    public function replaceForExercise(int $workoutId, int $exerciseId, array $sets): void
    {
        $this->pdo->beginTransaction();
        try {
            // l'ora della prima serie decide l'ordine degli esercizi: la teniamo, così modificare non sposta l'esercizio in fondo
            // COALESCE: se l'esercizio è nuovo (nessuna serie), usa l'ora attuale
            $stmt = $this->pdo->prepare('SELECT COALESCE(min(created_at), now()) FROM sets WHERE workout_id = ? AND exercise_id = ?');
            $stmt->execute([$workoutId, $exerciseId]);
            $firstAt = $stmt->fetchColumn();

            $this->pdo->prepare('DELETE FROM sets WHERE workout_id = ? AND exercise_id = ?')->execute([$workoutId, $exerciseId]);

            $insert = $this->pdo->prepare('INSERT INTO sets (workout_id, exercise_id, position, weight_kg, reps, created_at)
                                           VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($sets as $i => $set) {
                $insert->execute([$workoutId, $exerciseId, $i + 1, $set['weight_kg'], $set['reps'], $firstAt]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack(); // tutto o niente: le serie vecchie restano
            throw $e;
        }
    }

    /**
     * Tutte le serie dell'utente, dalla più vecchia: l'input di RecordService.
     * shortcut: legge tutto lo storico a ogni riepilogo, va bene per centinaia di allenamenti; oltre, salvare i record in una tabella.
     */
    public function historyForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.workout_id, w.started_at, s.exercise_id, e.name AS exercise, s.position, s.weight_kg, s.reps
             FROM sets s
             JOIN workouts w ON w.id = s.workout_id
             JOIN exercises e ON e.id = s.exercise_id
             WHERE w.user_id = ?
             ORDER BY w.started_at, s.workout_id, s.exercise_id, s.position'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    /** Le serie di un allenamento con il nome dell'esercizio, nell'ordine in cui sono stati fatti gli esercizi. */
    public function forWorkout(int $workoutId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.exercise_id, e.name, e.is_weighted_bodyweight, s.weight_kg, s.reps
             FROM sets s JOIN exercises e ON e.id = s.exercise_id
             WHERE s.workout_id = ?
             ORDER BY s.created_at, s.exercise_id, s.position'
        );
        $stmt->execute([$workoutId]);

        return $stmt->fetchAll();
    }
}
