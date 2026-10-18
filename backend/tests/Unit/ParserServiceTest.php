<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Ai\LlmException;
use App\Service\ParserService;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeLlmClient;

final class ParserServiceTest extends TestCase
{
    private const EXISTING = [['id' => 12, 'name' => 'Dip zavorrati', 'is_weighted_bodyweight' => true]];

    private function parseWith(string $aiAnswer, string $text = 'dip 45 per 8', ?string $current = null, ?array $pending = null): array
    {
        return (new ParserService(new FakeLlmClient($aiAnswer)))->parse($text, self::EXISTING, $current, $pending);
    }

    public function test_valid_sets_answer_is_normalized(): void
    {
        $result = $this->parseWith('{"type":"sets","exercise":"dip  con zavorra","typed_name":"dip con zavorra",
            "matched_existing_id":null,"weighted":true,"sets":[{"weight_kg":"45","reps":8},{"weight_kg":"42,5","reps":"6"}]}');

        $this->assertSame([
            'type' => 'sets',
            'exercise' => 'Dip con zavorra',
            'typed_name' => 'dip con zavorra',
            'matched_existing_id' => null,
            'weighted' => true,
            'sets' => [['weight_kg' => 45.0, 'reps' => 8], ['weight_kg' => 42.5, 'reps' => 6]],
        ], $result);
    }

    public function test_matched_exercise_uses_the_existing_name(): void
    {
        $result = $this->parseWith('{"type":"exercise","exercise":"Dips","typed_name":"dips","matched_existing_id":12,"weighted":false}');

        $this->assertSame('exercise', $result['type']);
        $this->assertSame(12, $result['matched_existing_id']);
        $this->assertSame('Dip zavorrati', $result['exercise']); // il nome salvato, non quello scritto
        $this->assertSame('dips', $result['typed_name']);
        $this->assertTrue($result['weighted']); // come l'esercizio esistente
    }

    public function test_unknown_matched_id_is_dropped(): void
    {
        $result = $this->parseWith('{"type":"exercise","exercise":"Panca","typed_name":"panca","matched_existing_id":999,"weighted":false}');

        $this->assertNull($result['matched_existing_id']);
        $this->assertSame('Panca', $result['exercise']);
    }

    public function test_sets_without_exercise_use_the_current_one(): void
    {
        $result = $this->parseWith('{"type":"sets","sets":[{"weight_kg":45,"reps":8}]}', '45 per 8', 'Dip zavorrati');

        $this->assertSame('Dip zavorrati', $result['exercise']);
        $this->assertSame(12, $result['matched_existing_id']);
    }

    public function test_missing_typed_name_falls_back_to_exercise_name(): void
    {
        $result = $this->parseWith('{"type":"sets","exercise":null,"typed_name":null,"sets":[{"weight_kg":45,"reps":8}]}', '45x8', 'Dip zavorrati');

        $this->assertSame('Dip zavorrati', $result['typed_name']);
    }

    public function test_prompt_asks_typed_name_without_numbers(): void
    {
        $fake = new FakeLlmClient('{"type":"question","text":"?"}');

        (new ParserService($fake))->parse('45x8', [], null, null);

        $this->assertStringContainsString('"typed_name": solo il nome dell\'esercizio', $fake->lastSystem);
    }

    public function test_prompt_explains_weighted_is_only_for_bodyweight(): void
    {
        $fake = new FakeLlmClient('{"type":"question","text":"?"}');

        (new ParserService($fake))->parse('panca 70 per 8', [], null, null);

        $this->assertStringContainsString('"weighted": true SOLO per esercizi a corpo libero', $fake->lastSystem);
    }

    public function test_question_is_passed_through(): void
    {
        $result = $this->parseWith('{"type":"question","text":"8 sono le ripetizioni o i kg?"}');

        $this->assertSame(['type' => 'question', 'text' => '8 sono le ripetizioni o i kg?'], $result);
    }

    public function test_out_of_range_answer_becomes_question(): void
    {
        $result = $this->parseWith('{"type":"sets","exercise":"Dip","sets":[{"weight_kg":1000,"reps":8}]}');

        $this->assertSame('question', $result['type']);
        $this->assertStringContainsString('Es. 45 kg × 8', $result['text']);
    }

    public function test_broken_json_becomes_question(): void
    {
        $this->assertSame('question', $this->parseWith('{"type":"sets", ...')['type']);
    }

    public function test_unknown_type_becomes_question(): void
    {
        $this->assertSame('question', $this->parseWith('{"type":"boh"}')['type']);
    }

    public function test_sets_without_any_exercise_become_question(): void
    {
        $this->assertSame('question', $this->parseWith('{"type":"sets","sets":[{"weight_kg":45,"reps":8}]}')['type']);
    }

    public function test_prompt_contains_existing_exercises_and_pending_sets(): void
    {
        $fake = new FakeLlmClient('{"type":"question","text":"?"}');

        (new ParserService($fake))->parse('la seconda erano 7', self::EXISTING, 'Dip zavorrati', [['weight_kg' => 45.0, 'reps' => 8], ['weight_kg' => 45.0, 'reps' => 6]]);

        $this->assertStringContainsString('12: Dip zavorrati', $fake->lastSystem);
        $this->assertStringContainsString('1) 45 kg × 8', $fake->lastSystem);
        $this->assertStringContainsString('2) 45 kg × 6', $fake->lastSystem);
        $this->assertSame('la seconda erano 7', $fake->lastUser);
    }

    public function test_llm_failure_is_rethrown(): void
    {
        $this->expectException(LlmException::class);

        (new ParserService(new FakeLlmClient(new LlmException('timeout'))))->parse('dip', [], null, null);
    }
}
