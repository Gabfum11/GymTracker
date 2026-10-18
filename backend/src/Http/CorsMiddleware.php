<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CORS: dice al browser che l'app Flutter (su un altro indirizzo, es. GitHub Pages)
 * può chiamare questa API. Senza, il browser blocca le risposte.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseFactoryInterface $responses, private string $allowedOrigin)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // "preflight": prima di un PUT o di una richiesta con token, il browser chiede il permesso con OPTIONS
        $response = $request->getMethod() === 'OPTIONS'
            ? $this->responses->createResponse(204)
            : $handler->handle($request);

        return $response
            ->withHeader('Access-Control-Allow-Origin', $this->allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
}
