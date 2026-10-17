<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Scrive un valore PHP come JSON nella risposta.
 * Slim non ha un helper per il JSON (FastAPI lo fa da solo): lo centralizziamo qui.
 */
final class JsonResponse
{
    public static function with(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
