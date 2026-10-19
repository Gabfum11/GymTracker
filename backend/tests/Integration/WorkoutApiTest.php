<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\AppFactory;
use Slim\App;
use Tests\Support\DbTestCase;

final class WorkoutApiTest extends DbTestCase
{
    private App $app;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp(); // prepara il database pulito (DbTestCase)
        $this->app = AppFactory::create(['jwt_secret' => self::JWT_SECRET], $this->pdo);
        $this->token = $this->tokenFor($this->createUser());
    }

    /** Una richiesta come utente loggato. */
    private function call(string $method, string $path, ?array $body = null): array
    {
        return $this->request($this->app, $method, $path, $body, $this->token);
    }

    private function createExercise(string $name): int
    {
        [, $exercise] = $this->call('POST', '/exercises', ['name' => $name, 'is_weighted_bodyweight' => false]);

        return $exercise['id'];
    }

    public function test_start_is_idempotent(): void
    {
        [$status, $first] = $this->call('POST', '/workouts');
        [, $second] = $this->call('POST', '/workouts');

        $this->assertSame(200, $status);
        $this->assertSame($first['id'], $second['id']);
        $this->assertNull($first['ended_at']);
    }

    public function test_current_is_null_without_open_workout(): void
    {
        [$status, $body] = $this->call('GET', '/workouts/current');

        $this->assertSame(200, $status);
        $this->assertNull($body);
    }

    public function test_current_restores_open_workout(): void
    {
        [, $workout] = $this->call('POST', '/workouts');
        $dip = $this->createExercise('Dip zavorrati');
        $this->call('PUT', "/workouts/{$workout['id']}/exercises/$dip/sets", ['sets' => [
            ['weight_kg' => 45, 'reps' => 8],
            ['weight_kg' => '42,5', 'reps' => 6],
        ]]);

        [, $current] = $this->call('GET', '/workouts/current');

        $this->assertSame($workout['id'], $current['id']);
        $this->assertSame('Dip zavorrati', $current['exercises'][0]['name']);
        $this->assertSame(
            [['weight_kg' => 45.0, 'reps' => 8, 'is_record' => false], ['weight_kg' => 42.5, 'reps' => 6, 'is_record' => false]],
            $current['exercises'][0]['sets'],
        );
        $this->assertSame(2, $current['set_count']);
        $this->assertSame(1, $current['exercise_count']);
    }

    public function test_put_sets_replaces_not_appends(): void
    {
        [, $workout] = $this->call('POST', '/workouts');
        $dip = $this->createExercise('Dip');
        $url = "/workouts/{$workout['id']}/exercises/$dip/sets";

        $this->call('PUT', $url, ['sets' => array_fill(0, 3, ['weight_kg' => 45, 'reps' => 8])]);
        [$status, $summary] = $this->call('PUT', $url, ['sets' => array_fill(0, 2, ['weight_kg' => 45, 'reps' => 8])]);

        $this->assertSame(200, $status);
        $this->assertSame(2, $summary['set_count']);
    }

    public function test_put_empty_list_removes_exercise(): void
    {
        [, $workout] = $this->call('POST', '/workouts');
        $dip = $this->createExercise('Dip');
        $url = "/workouts/{$workout['id']}/exercises/$dip/sets";
        $this->call('PUT', $url, ['sets' => [['weight_kg' => 45, 'reps' => 8]]]);

        [, $summary] = $this->call('PUT', $url, ['sets' => []]);

        $this->assertSame([], $summary['exercises']);
    }

    public function test_invalid_sets_return_400_and_change_nothing(): void
    {
        [, $workout] = $this->call('POST', '/workouts');
        $dip = $this->createExercise('Dip');
        $url = "/workouts/{$workout['id']}/exercises/$dip/sets";
        $this->call('PUT', $url, ['sets' => [['weight_kg' => 45, 'reps' => 8]]]);

        [$status, $body] = $this->call('PUT', $url, ['sets' => [['weight_kg' => 45, 'reps' => 0]]]);

        $this->assertSame(400, $status);
        $this->assertSame('Serie 1: le ripetizioni devono essere un numero intero tra 1 e 100', $body['error']);
        [, $current] = $this->call('GET', '/workouts/current');
        $this->assertSame(8, $current['exercises'][0]['sets'][0]['reps']);
    }

    public function test_finish_closes_workout(): void
    {
        [, $workout] = $this->call('POST', '/workouts');

        [$status, $finished] = $this->call('POST', "/workouts/{$workout['id']}/finish");

        $this->assertSame(200, $status);
        $this->assertNotNull($finished['ended_at']);
        [, $current] = $this->call('GET', '/workouts/current');
        $this->assertNull($current);
    }

    public function test_cancel_deletes_open_workout(): void
    {
        [, $workout] = $this->call('POST', '/workouts');
        $dip = $this->createExercise('Dip');
        $this->call('PUT', "/workouts/{$workout['id']}/exercises/$dip/sets", ['sets' => [['weight_kg' => 45, 'reps' => 8]]]);

        [$status] = $this->call('DELETE', "/workouts/{$workout['id']}");

        $this->assertSame(204, $status);
        [, $current] = $this->call('GET', '/workouts/current');
        $this->assertNull($current);
        $this->assertSame(0, (int) $this->pdo->query('SELECT count(*) FROM sets')->fetchColumn());
    }

    public function test_cancel_finished_workout_returns_409(): void
    {
        [, $workout] = $this->call('POST', '/workouts');
        $this->call('POST', "/workouts/{$workout['id']}/finish");

        [$status] = $this->call('DELETE', "/workouts/{$workout['id']}");

        $this->assertSame(409, $status);
    }

    public function test_put_on_finished_workout_returns_409(): void
    {
        [, $workout] = $this->call('POST', '/workouts');
        $dip = $this->createExercise('Dip');
        $this->call('POST', "/workouts/{$workout['id']}/finish");

        [$status] = $this->call('PUT', "/workouts/{$workout['id']}/exercises/$dip/sets", ['sets' => [['weight_kg' => 45, 'reps' => 8]]]);

        $this->assertSame(409, $status);
    }

    public function test_unknown_workout_returns_404(): void
    {
        $dip = $this->createExercise('Dip');

        [$status] = $this->call('PUT', "/workouts/999/exercises/$dip/sets", ['sets' => [['weight_kg' => 45, 'reps' => 8]]]);

        $this->assertSame(404, $status);
    }

    public function test_title_uses_first_two_exercises_and_keeps_order_after_edit(): void
    {
        [, $workout] = $this->call('POST', '/workouts');
        $base = "/workouts/{$workout['id']}/exercises";
        foreach (['Dip', 'Panca', 'Curl'] as $name) {
            $this->call('PUT', "$base/{$this->createExercise($name)}/sets", ['sets' => [['weight_kg' => 20, 'reps' => 8]]]);
        }
        $dip = $this->createExercise('Dip'); // stesso esercizio, già esistente

        [, $summary] = $this->call('PUT', "$base/$dip/sets", ['sets' => [['weight_kg' => 25, 'reps' => 8]]]); // modifico il primo

        $this->assertSame('Dip & Panca', $summary['title']);
        $this->assertSame(['Dip', 'Panca', 'Curl'], array_column($summary['exercises'], 'name'));
    }

    public function test_summary_marks_the_record_set(): void
    {
        $dip = $this->createExercise('Dip');
        [, $first] = $this->call('POST', '/workouts');
        $this->call('PUT', "/workouts/{$first['id']}/exercises/$dip/sets", ['sets' => [['weight_kg' => 40, 'reps' => 8]]]);
        $this->call('POST', "/workouts/{$first['id']}/finish");

        [, $second] = $this->call('POST', '/workouts');
        [, $summary] = $this->call('PUT', "/workouts/{$second['id']}/exercises/$dip/sets", ['sets' => [
            ['weight_kg' => 42.5, 'reps' => 8],
            ['weight_kg' => 45, 'reps' => 6],
        ]]);

        $this->assertSame([false, true], array_column($summary['exercises'][0]['sets'], 'is_record'));
        $this->assertCount(1, $summary['records']);
        $this->assertSame(['exercise' => 'Dip', 'metric' => 'kg', 'weight_kg' => 45.0, 'reps' => 6], array_intersect_key(
            $summary['records'][0],
            array_flip(['exercise', 'metric', 'weight_kg', 'reps']),
        ));
    }

    public function test_post_exercise_reuses_existing_ignoring_case(): void
    {
        [$created, $first] = $this->call('POST', '/exercises', ['name' => 'Dip', 'is_weighted_bodyweight' => true]);
        [$reused, $second] = $this->call('POST', '/exercises', ['name' => '  dip ', 'is_weighted_bodyweight' => true]);

        $this->assertSame(201, $created);
        $this->assertSame(200, $reused);
        $this->assertSame($first['id'], $second['id']);
        [, $list] = $this->call('GET', '/exercises');
        $this->assertCount(1, $list);
    }
}
