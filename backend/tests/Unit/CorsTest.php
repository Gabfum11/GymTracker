<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\AppFactory;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CorsTest extends TestCase
{
    public function test_responses_allow_the_configured_origin(): void
    {
        $app = AppFactory::create(['cors_origin' => 'https://gabfum11.github.io']);

        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/health'));

        $this->assertSame('https://gabfum11.github.io', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function test_preflight_options_request_is_answered(): void
    {
        $app = AppFactory::create(['cors_origin' => '*']);
        $request = (new ServerRequestFactory())->createServerRequest('OPTIONS', '/workouts/1/exercises/2/sets')
            ->withHeader('Origin', 'http://localhost:5000')
            ->withHeader('Access-Control-Request-Method', 'PUT');

        $response = $app->handle($request);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('PUT', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('Authorization', $response->getHeaderLine('Access-Control-Allow-Headers'));
    }

    public function test_error_responses_also_have_cors_headers(): void
    {
        $app = AppFactory::create(['cors_origin' => '*']);

        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/non-esiste'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }
}
