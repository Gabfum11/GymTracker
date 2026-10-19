<?php

declare(strict_types=1);

namespace App\Http;

use App\Service\AuthService;
use App\Service\UnauthorizedException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Davanti a tutte le rotte protette: legge "Authorization: Bearer <access token>",
 * lo verifica e passa al controller l'id dell'utente. Senza token valido: 401, e il controller non parte nemmeno.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private AuthService $auth)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        $userId = str_starts_with($header, 'Bearer ') //se il testo inzia con Bearer restituisce true
            ? $this->auth->userIdForAccessToken(substr($header, 7))
            : null;

        if ($userId === null) {
            throw new UnauthorizedException('Accesso richiesto');
        }

        return $handler->handle($request->withAttribute('user_id', $userId));
    }
}
