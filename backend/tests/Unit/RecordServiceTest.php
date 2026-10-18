<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Service\RecordService;
use PHPUnit\Framework\TestCase;

final class RecordServiceTest extends TestCase
{
    /**
     * Costruisce lo storico come lo legge il database: una riga per serie.
     * $workouts = [workout_id => [esercizio => [[kg, rip], ...]]], in ordine di tempo.
     */
    private static function history(array $workouts): array
    {
        $rows = [];
        $exerciseIds = [];
        foreach ($workouts as $workoutId => $exercises) {
            foreach ($exercises as $name => $sets) {
                $exerciseIds[$name] ??= count($exerciseIds) + 1;
                foreach ($sets as $i => [$kg, $reps]) {
                    $rows[] = [
                        'workout_id' => $workoutId,
                        'started_at' => sprintf('2026-10-%02d 10:00:00+00', $workoutId),
                        'exercise_id' => $exerciseIds[$name],
                        'exercise' => $name,
                        'position' => $i + 1,
                        'weight_kg' => (string) $kg, // dal database NUMERIC arriva come testo
                        'reps' => $reps,
                    ];
                }
            }
        }

        return $rows;
    }

    public function test_first_session_is_not_a_record(): void
    {
        $this->assertSame([], RecordService::compute(self::history([1 => ['Dip' => [[40, 8]]]])));
    }

    public function test_heavier_session_is_one_record_even_with_two_better_sets(): void
    {
        $records = RecordService::compute(self::history([
            1 => ['Dip' => [[40, 8]]],
            2 => ['Dip' => [[42.5, 8], [45, 6]]],
        ]));

        $this->assertCount(1, $records);
        $this->assertSame(
            ['workout_id' => 2, 'exercise_id' => 1, 'set_index' => 1, 'exercise' => 'Dip', 'date' => '2026-10-02', 'metric' => 'kg', 'weight_kg' => 45.0, 'reps' => 6],
            $records[0],
        );
    }

    public function test_equal_weight_is_not_a_record(): void
    {
        $this->assertSame([], RecordService::compute(self::history([
            1 => ['Dip' => [[40, 8]]],
            2 => ['Dip' => [[40, 10]]],
        ])));
    }

    public function test_best_is_remembered_across_sessions(): void
    {
        $records = RecordService::compute(self::history([
            1 => ['Dip' => [[40, 8]]],
            2 => ['Dip' => [[45, 8]]],
            3 => ['Dip' => [[42.5, 8]]], // più di 40 ma meno di 45: non è un record
        ]));

        $this->assertSame([2], array_column($records, 'workout_id'));
    }

    public function test_bodyweight_exercise_uses_reps(): void
    {
        $records = RecordService::compute(self::history([
            1 => ['Trazioni' => [[0, 8], [0, 7]]],
            2 => ['Trazioni' => [[0, 10], [0, 9]]],
        ]));

        $this->assertCount(1, $records);
        $this->assertSame('reps', $records[0]['metric']);
        $this->assertSame(10, $records[0]['reps']);
        $this->assertSame(0, $records[0]['set_index']);
    }

    public function test_switches_to_kg_once_weight_appears(): void
    {
        $records = RecordService::compute(self::history([
            1 => ['Trazioni' => [[0, 10]]],
            2 => ['Trazioni' => [[0, 12]]],  // più ripetizioni, ma ormai si misura in kg: non è record
            3 => ['Trazioni' => [[10, 6]]],
        ]));

        $this->assertSame([3], array_column($records, 'workout_id'));
        $this->assertSame('kg', $records[0]['metric']);
    }

    public function test_records_are_per_exercise(): void
    {
        $records = RecordService::compute(self::history([
            1 => ['Dip' => [[40, 8]], 'Panca' => [[80, 5]]],
            2 => ['Dip' => [[45, 8]], 'Panca' => [[85, 5]]],
        ]));

        $this->assertSame(['Dip', 'Panca'], array_column($records, 'exercise'));
    }

    public function test_same_weight_more_reps_picks_the_best_set(): void
    {
        $records = RecordService::compute(self::history([
            1 => ['Dip' => [[40, 8]]],
            2 => ['Dip' => [[45, 6], [45, 8]]],
        ]));

        $this->assertSame(1, $records[0]['set_index']); // 45 × 8 è meglio di 45 × 6
    }
}
