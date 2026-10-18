<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Calcola i record dallo storico delle serie (spec §5.3). Niente si salva: si ricalcola ogni volta.
 *
 * Regole:
 * - si misura in kg se l'esercizio ha mai avuto una serie con peso > 0, altrimenti in ripetizioni;
 * - la prima sessione di un esercizio non è un record (è il punto di partenza);
 * - una sessione è un record se supera il migliore di TUTTE le sessioni precedenti;
 * - al massimo un record per esercizio per allenamento: la serie migliore.
 */
final class RecordService
{
    /**
     * @param list<array> $history righe {workout_id, started_at, exercise_id, exercise, position, weight_kg, reps},
     *                             ordinate per started_at e poi per position (SetRepository::historyForUser)
     * @return list<array{workout_id: int, exercise_id: int, set_index: int, exercise: string, date: string, metric: string, weight_kg: float, reps: int}>
     */
    public static function compute(array $history): array
    {
        // esercizio → allenamento → serie, mantenendo l'ordine di tempo
        $byExercise = [];
        foreach ($history as $row) {
            $byExercise[(int) $row['exercise_id']][(int) $row['workout_id']][] = $row;
        }

        $records = [];
        foreach ($byExercise as $exerciseId => $sessions) {
            $usesKg = false;
            foreach ($sessions as $sets) {
                foreach ($sets as $set) {
                    $usesKg = $usesKg || (float) $set['weight_kg'] > 0;
                }
            }

            $best = null; // il migliore finora; null = nessuna sessione ancora vista
            foreach ($sessions as $workoutId => $sets) {
                $index = self::bestSetIndex($sets, $usesKg);
                $top = $sets[$index];
                $value = $usesKg ? (float) $top['weight_kg'] : (int) $top['reps'];

                if ($best !== null && $value > $best) {
                    $records[] = [
                        'workout_id' => $workoutId,
                        'exercise_id' => $exerciseId,
                        'set_index' => $index,
                        'exercise' => $top['exercise'],
                        'date' => (new DateTimeImmutable($top['started_at']))
                            ->setTimezone(new DateTimeZone('Europe/Rome'))->format('Y-m-d'),
                        'metric' => $usesKg ? 'kg' : 'reps',
                        'weight_kg' => (float) $top['weight_kg'],
                        'reps' => (int) $top['reps'],
                    ];
                }
                $best = $best === null ? $value : max($best, $value);
            }
        }

        return $records;
    }

    /** La serie migliore di una sessione: più kg (a parità, più ripetizioni), oppure più ripetizioni. */
    private static function bestSetIndex(array $sets, bool $usesKg): int
    {
        $bestIndex = 0;
        foreach ($sets as $i => $set) {
            $current = [$usesKg ? (float) $set['weight_kg'] : 0.0, (int) $set['reps']];
            $champion = [$usesKg ? (float) $sets[$bestIndex]['weight_kg'] : 0.0, (int) $sets[$bestIndex]['reps']];
            if ($current > $champion) { // confronto tra array: prima i kg, poi le ripetizioni
                $bestIndex = $i;
            }
        }

        return $bestIndex;
    }
}
