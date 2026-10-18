<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;

/**
 * Costruisce il "WorkoutSummary": l'allenamento come lo vede l'app
 * (titolo, durata, conteggi, esercizi con le loro serie). Niente SQL qui: riceve i dati già letti.
 */
final class SummaryService
{
    /**
     * @param array $workout riga della tabella workouts
     * @param array $sets    righe di SetRepository::forWorkout(), già in ordine
     * @param array $records record dell'utente (RecordService::compute): si tengono quelli di questo allenamento
     */
    public static function build(array $workout, array $sets, array $records = []): array
    {
        $records = array_values(array_filter($records, fn ($r) => $r['workout_id'] === (int) $workout['id']));
        // per segnare la serie record: [id esercizio => posizione della serie]
        $recordSet = array_column($records, 'set_index', 'exercise_id');

        // raggruppa le serie per esercizio, mantenendo l'ordine
        $exercises = [];
        foreach ($sets as $set) {
            $id = (int) $set['exercise_id'];
            $exercises[$id] ??= [
                'id' => $id,
                'name' => $set['name'],
                'is_weighted_bodyweight' => (bool) $set['is_weighted_bodyweight'],
                'sets' => [],
            ];
            $exercises[$id]['sets'][] = [
                'weight_kg' => (float) $set['weight_kg'], // PostgreSQL restituisce NUMERIC come testo ("45.00")
                'reps' => (int) $set['reps'],
                'is_record' => ($recordSet[$id] ?? null) === count($exercises[$id]['sets']),
            ];
        }
        $exercises = array_values($exercises);

        $names = array_column($exercises, 'name');
        $started = new DateTimeImmutable($workout['started_at']);
        $ended = $workout['ended_at'] ? new DateTimeImmutable($workout['ended_at']) : null;

        return [
            'id' => (int) $workout['id'],
            'title' => $names === [] ? 'Allenamento' : implode(' & ', array_slice($names, 0, 2)),
            'started_at' => $started->format(DATE_ATOM),
            'ended_at' => $ended?->format(DATE_ATOM),
            'duration_minutes' => intdiv(($ended ?? new DateTimeImmutable())->getTimestamp() - $started->getTimestamp(), 60),
            'set_count' => count($sets),
            'exercise_count' => count($exercises),
            // all'app servono solo questi campi (gli altri erano per trovare la serie)
            'records' => array_map(fn ($r) => array_intersect_key($r, array_flip(['exercise', 'date', 'metric', 'weight_kg', 'reps'])), $records),
            'exercises' => $exercises,
        ];
    }
}
