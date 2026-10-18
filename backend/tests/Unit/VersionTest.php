<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\AppFactory;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class VersionTest extends TestCase          // ← nome cambiato
{
    public function test_version_returns_number(): void   // ← nome cambiato
    {
        $app = AppFactory::create();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/version');   // ← /version

        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(['version' => '0.1.0'], json_decode((string) $response->getBody(), true));   // ← risultato atteso
    }
}