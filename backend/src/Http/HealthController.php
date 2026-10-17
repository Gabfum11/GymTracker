<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /health: risponde se il server è acceso. Utile anche per svegliare Render. */
final class HealthController
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponse::with($response, ['ok' => true]);
    }
}
