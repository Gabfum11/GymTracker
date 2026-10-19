<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Ai\LlmException;
use App\AppFactory;
use Tests\Support\DbTestCase;
use Tests\Support\FakeLlmClient;

final class ParseApiTest extends DbTestCase
{
    private function parse(FakeLlmClient $llm, array $body, array $config = []): array
    {
        $app = AppFactory::create(['jwt_secret' => self::JWT_SECRET] + $config, $this->pdo, $llm);

        return $this->request($app, 'POST', '/parse', $body, $this->tokenFor($this->createUser()));
    }

    public function test_parse_returns_the_parser_result(): void
    {
        $llm = new FakeLlmClient('{"type":"sets","exercise":"Dip","typed_name":"dip","matched_existing_id":null,"weighted":false,"sets":[{"weight_kg":45,"reps":8}]}');

        [$status, $body] = $this->parse($llm, ['text' => 'dip 45 per 8']);

        $this->assertSame(200, $status);
        $this->assertSame('sets', $body['type']);
        $this->assertSame([['weight_kg' => 45.0, 'reps' => 8]], $body['sets']);
    }

    public function test_parse_returns_503_when_llm_fails(): void
    {
        [$status, $body] = $this->parse(new FakeLlmClient(new LlmException('timeout')), ['text' => 'dip 45 per 8']);

        $this->assertSame(503, $status);
        $this->assertSame(['error' => 'Il bot non risponde, riprova tra poco'], $body);
    }

    public function test_empty_text_returns_400(): void
    {
        [$status] = $this->parse(new FakeLlmClient('{}'), ['text' => '   ']);

        $this->assertSame(400, $status);
    }

    public function test_user_exercises_are_sent_to_the_ai(): void
    {
        $llm = new FakeLlmClient('{"type":"question","text":"?"}');
        $app = AppFactory::create(['jwt_secret' => self::JWT_SECRET], $this->pdo, $llm);
        $userId = $this->createUser();
        $this->pdo->prepare("INSERT INTO exercises (user_id, name) VALUES (?, 'Panca piana')")->execute([$userId]);

        $this->request($app, 'POST', '/parse', ['text' => 'panca 80 per 5'], $this->tokenFor($userId));

        $this->assertStringContainsString('Panca piana', $llm->lastSystem);
    }

    public function test_parse_over_daily_limit_returns_429(): void
    {
        $llm = new FakeLlmClient('{"type":"question","text":"?"}');
        $app = AppFactory::create(['jwt_secret' => self::JWT_SECRET, 'parse_limit' => 2], $this->pdo, $llm);
        $token = $this->tokenFor($this->createUser());

        $this->request($app, 'POST', '/parse', ['text' => 'uno'], $token);
        $this->request($app, 'POST', '/parse', ['text' => 'due'], $token);
        [$status, $body] = $this->request($app, 'POST', '/parse', ['text' => 'tre'], $token);

        $this->assertSame(429, $status);
        $this->assertSame('Hai raggiunto il limite di messaggi per oggi', $body['error']);
    }
}
