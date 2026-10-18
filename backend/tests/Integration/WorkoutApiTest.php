<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\AppFactory;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\DbTestCase;

final class WorkoutApiTest extends DbTestCase
{
    private App $app;

    protected function setUp(): void
    {
        parent::setUp(); // prepara il database pulito (DbTestCase)
        $this->app = AppFactory::create(['dev_user_id' => $this->createUser()], $this->pdo);
    }

    /** Manda una richiesta all'app e restituisce [codice HTTP, corpo JSON già decodificato]. */
    private function call(string $method, string $path, ?array $body = null): array
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path);
        if ($body !== null) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request->getBody()->write(json_encode($body));
        }
        $response = $this->app->handle($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
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
