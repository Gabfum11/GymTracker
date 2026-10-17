<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\AppFactory;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class HealthTest extends TestCase
{
    public function test_health_returns_ok(): void
    {
        $app = AppFactory::create();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');

        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(['ok' => true], json_decode((string) $response->getBody(), true));
    }
}
