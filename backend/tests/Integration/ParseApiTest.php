<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Ai\LlmException;
use App\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\DbTestCase;
use Tests\Support\FakeLlmClient;

final class ParseApiTest extends DbTestCase
{
    private function parse(FakeLlmClient $llm, array $body): array
    {
        $app = AppFactory::create(['dev_user_id' => $this->createUser()], $this->pdo, $llm);
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/parse')
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode($body));
        $response = $app->handle($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
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
        $app = AppFactory::create(['dev_user_id' => $userId = $this->createUser()], $this->pdo, $llm);
        $this->pdo->prepare("INSERT INTO exercises (user_id, name) VALUES (?, 'Panca piana')")->execute([$userId]);
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/parse')->withHeader('Content-Type', 'application/json');
        $request->getBody()->write('{"text":"panca 80 per 5"}');

        $app->handle($request);

        $this->assertStringContainsString('Panca piana', $llm->lastSystem);
    }
}
