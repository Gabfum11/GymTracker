<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ExerciseRepository;
use App\Repository\SetRepository;
use App\Repository\WorkoutRepository;

/** Le regole degli allenamenti: uno solo aperto, si modifica solo se aperto, si vedono solo i propri. */
final class WorkoutService
{
    public function __construct(
        private WorkoutRepository $workouts,
        private ExerciseRepository $exercises,
        private SetRepository $sets,
    ) {
    }

    /** Avvia un allenamento; se ce n'è già uno aperto restituisce quello (premere due volte non ne crea due). */
    public function start(int $userId): array
    {
        $workout = $this->workouts->findOpen($userId) ?? $this->workouts->create($userId);

        return $this->summary($workout);
    }

    public function current(int $userId): ?array
    {
        $workout = $this->workouts->findOpen($userId);

        return $workout ? $this->summary($workout) : null;
    }

    public function saveSets(int $userId, int $workoutId, int $exerciseId, mixed $rawSets): array
    {
        $workout = $this->ownedWorkout($userId, $workoutId);
        if ($workout['ended_at'] !== null) {
            throw new ConflictException('Questo allenamento è già chiuso');
        }
        if ($this->exercises->find($userId, $exerciseId) === null) {
            throw new NotFoundException('Esercizio non trovato');
        }

        // lista vuota = l'utente ha tolto tutte le serie: l'esercizio sparisce dall'allenamento
        $sets = $rawSets === [] ? [] : SetValidator::sets($rawSets);
        $this->sets->replaceForExercise($workoutId, $exerciseId, $sets);

        return $this->summary($workout);
    }

    public function finish(int $userId, int $workoutId): array
    {
        $this->ownedWorkout($userId, $workoutId);
        $this->workouts->finish($workoutId);

        return $this->summary($this->workouts->find($userId, $workoutId));
    }

    private function ownedWorkout(int $userId, int $workoutId): array
    {
        return $this->workouts->find($userId, $workoutId)
            ?? throw new NotFoundException('Allenamento non trovato');
    }

    private function summary(array $workout): array
    {
        return SummaryService::build(
            $workout,
            $this->sets->forWorkout((int) $workout['id']),
            RecordService::compute($this->sets->historyForUser((int) $workout['user_id'])),
        );
    }
}
