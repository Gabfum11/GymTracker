<?php

declare(strict_types=1);

namespace App\Http;

use App\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Le rotte /auth/* e /me. Legge la richiesta, chiama AuthService, risponde. */
final class AuthController
{
    public function __construct(private AuthService $auth, private bool $trustProxy = false)
    {
    }

    /** POST /auth/register {name, email, password} → 201, e arriva il codice via email */
    public function register(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $this->auth->register($b['name'] ?? '', $b['email'] ?? '', $b['password'] ?? '', $this->ip($request));

        return JsonResponse::with($response, ['message' => 'Ti abbiamo mandato un codice via email'], 201);
    }

    /** POST /auth/verify-email {email, code} → sessione */
    public function verifyEmail(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();

        return JsonResponse::with($response, $this->auth->verifyEmail($b['email'] ?? '', $b['code'] ?? ''));
    }

    /** POST /auth/resend-code {email} → 204 */
    public function resendCode(Request $request, Response $response): Response
    {
        $this->auth->resendCode(((array) $request->getParsedBody())['email'] ?? '');

        return $response->withStatus(204);
    }

    /** POST /auth/login {email, password} → sessione */
    public function login(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();

        return JsonResponse::with($response, $this->auth->login($b['email'] ?? '', $b['password'] ?? '', $this->ip($request)));
    }

    /** POST /auth/refresh {refresh_token} → sessione nuova */
    public function refresh(Request $request, Response $response): Response
    {
        return JsonResponse::with($response, $this->auth->refresh(((array) $request->getParsedBody())['refresh_token'] ?? ''));
    }

    /** POST /auth/logout {refresh_token} → 204 */
    public function logout(Request $request, Response $response): Response
    {
        $this->auth->logout(((array) $request->getParsedBody())['refresh_token'] ?? '');

        return $response->withStatus(204);
    }

    /** POST /auth/logout-all (serve il login) → 204 */
    public function logoutAll(Request $request, Response $response): Response
    {
        $this->auth->logoutAll($request->getAttribute('user_id'));

        return $response->withStatus(204);
    }

    /** POST /auth/forgot-password {email} → 204 (sempre) */
    public function forgotPassword(Request $request, Response $response): Response
    {
        $this->auth->forgotPassword(((array) $request->getParsedBody())['email'] ?? '');

        return $response->withStatus(204);
    }

    /** POST /auth/reset-code {email, code} → {reset_token} */
    public function resetCode(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();

        return JsonResponse::with($response, ['reset_token' => $this->auth->resetCode($b['email'] ?? '', $b['code'] ?? '')]);
    }

    /** POST /auth/reset-password {reset_token, password} → 204 */
    public function resetPassword(Request $request, Response $response): Response
    {
        $b = (array) $request->getParsedBody();
        $this->auth->resetPassword($b['reset_token'] ?? '', $b['password'] ?? '');

        return $response->withStatus(204);
    }

    /** GET /me */
    public function me(Request $request, Response $response): Response
    {
        return JsonResponse::with($response, $this->auth->me($request->getAttribute('user_id')));
    }

    /**
     * L'IP di chi chiama, per i limiti anti-abuso.
     * shortcut: dietro il proxy di Render si prende l'ultimo indirizzo di X-Forwarded-For (quello aggiunto da Render);
     * con un'altra infrastruttura va ricontrollato.
     */
    private function ip(Request $request): string
    {
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($this->trustProxy && $forwarded !== '') {
            $parts = array_map('trim', explode(',', $forwarded));

            return end($parts);
        }

        return $request->getServerParams()['REMOTE_ADDR'] ?? 'sconosciuto';
    }
}
