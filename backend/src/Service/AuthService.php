<?php

declare(strict_types=1);

namespace App\Service;

use App\Mail\Mailer;
use App\Repository\OtpRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;

/**
 * Le regole dell'accesso, come TrackIt (spec §6.3):
 * registrazione con codice via email, login, access token JWT da 15 minuti,
 * refresh token da 30 giorni ruotato a ogni uso, logout.
 */
final class AuthService
{
    private const REFRESH_DAYS = 30;
    private const CODE_MINUTES = 10;
    private const MAX_CODE_ATTEMPTS = 5;
    private const WRONG_LOGIN = 'Email o password errati';
    private const BAD_CODE = 'Codice scaduto o già usato: chiedine uno nuovo';

    public function __construct(
        private UserRepository $users,
        private RefreshTokenRepository $refreshTokens,
        private OtpRepository $codes,
        private TokenService $tokens,
        private Mailer $mailer,
        private RateLimiter $limiter,
    ) {
    }

    public function register(mixed $name, mixed $email, mixed $password, string $ip): void
    {
        $this->limiter->check("register:$ip", 5, 3600, 'Troppe registrazioni da questa rete, riprova più tardi');
        $name = trim((string) $name);
        $email = self::email($email);
        $password = self::password($password);
        if ($name === '' || mb_strlen($name) > 60) {
            throw new ValidationException('Il nome deve avere da 1 a 60 caratteri');
        }

        $existing = $this->users->findByEmail($email);
        if ($existing && $existing['is_verified']) {
            throw new ConflictException('Esiste già un account con questa email');
        }
        if ($existing) {
            // registrazione mai completata: si ricomincia da capo (come TrackIt)
            $this->users->delete((int) $existing['id']);
        }

        $user = $this->users->create($name, $email, password_hash($password, PASSWORD_ARGON2ID));
        $this->sendCode($user, 'verify');
    }

    /** Rimanda il codice di verifica. Non dice mai se l'email esiste (risponde sempre allo stesso modo). */
    public function resendCode(mixed $email): void
    {
        $user = $this->users->findByEmail(self::email($email));
        if ($user && !$user['is_verified']) {
            $this->sendCode($user, 'verify');
        }
    }

    public function verifyEmail(mixed $email, mixed $code): array
    {
        $user = $this->users->findByEmail(self::email($email)) ?? throw new ValidationException(self::BAD_CODE);
        $this->consumeCode($user, 'verify', (string) $code);
        $this->users->markVerified((int) $user['id']);

        return $this->openSession($user);
    }

    public function login(mixed $email, mixed $password, string $ip): array
    {
        $this->limiter->check("login:$ip", 10, 15 * 60, 'Troppi tentativi di accesso, riprova tra qualche minuto');
        $user = $this->users->findByEmail(self::email($email, strict: false));

        // stesso messaggio se l'email non esiste o la password è sbagliata: non si rivela chi è registrato
        if (!$user || !password_verify((string) $password, $user['password_hash'])) {
            throw new UnauthorizedException(self::WRONG_LOGIN);
        }
        if (!$user['is_verified']) {
            throw new ForbiddenException('Verifica prima la tua email');
        }

        return $this->openSession($user);
    }

    /** Scambia un refresh token con una coppia nuova. Il vecchio non vale più (rotazione). */
    public function refresh(mixed $refreshToken): array
    {
        $row = $this->refreshTokens->findByHash(hash('sha256', (string) $refreshToken));
        if (!$row || $row['expired']) {
            throw new UnauthorizedException('Sessione scaduta, accedi di nuovo');
        }
        if ($row['used_at'] !== null || !$this->refreshTokens->markUsed((int) $row['id'])) {
            // un refresh già usato che torna indietro è stato copiato: si chiude tutto (come TrackIt)
            $this->logoutAll((int) $row['user_id']);
            throw new UnauthorizedException('Sessione non valida, accedi di nuovo');
        }

        return $this->openSession($this->users->find((int) $row['user_id']));
    }

    public function logout(mixed $refreshToken): void
    {
        $this->refreshTokens->delete(hash('sha256', (string) $refreshToken));
    }

    /** Chiude tutte le sessioni: niente più refresh, e gli access token già emessi smettono di valere. */
    public function logoutAll(int $userId): void
    {
        $this->refreshTokens->deleteForUser($userId);
        $this->users->bumpTokenVersion($userId);
    }

    /** L'id dell'utente se l'access token è valido, altrimenti null. Lo usa AuthMiddleware. */
    public function userIdForAccessToken(string $jwt): ?int
    {
        $payload = $this->tokens->decode($jwt);
        // un token di reset password non apre l'app
        if ($payload === null || isset($payload['purpose'])) {
            return null;
        }
        $user = $this->users->find((int) $payload['sub']);

        return $user && (int) $user['token_version'] === $payload['ver'] ? (int) $user['id'] : null;
    }

    public function me(int $userId): array
    {
        return self::publicUser($this->users->find($userId));
    }

    private function openSession(array $user): array
    {
        $refresh = bin2hex(random_bytes(32)); // 64 caratteri casuali
        $this->refreshTokens->create((int) $user['id'], hash('sha256', $refresh), self::REFRESH_DAYS);
        $fresh = $this->users->find((int) $user['id']); // token_version aggiornato

        return [
            'access_token' => $this->tokens->accessToken((int) $fresh['id'], (int) $fresh['token_version']),
            'refresh_token' => $refresh,
            'user' => self::publicUser($fresh),
        ];
    }

    private function sendCode(array $user, string $purpose): void
    {
        $this->limiter->check("otp:{$user['id']}", 3, 3600, 'Hai chiesto troppi codici, riprova tra un\'ora');
        $code = (string) random_int(100000, 999999); // random_int è crittografico, rand() no
        $this->codes->replace((int) $user['id'], $purpose, $code, self::CODE_MINUTES);

        $subject = $purpose === 'verify' ? 'Il tuo codice per GymTracker' : 'Recupero password GymTracker';
        $this->mailer->send($user['email'], $subject, "Ciao {$user['name']},\n\nil tuo codice è: $code\n\nVale "
            . self::CODE_MINUTES . " minuti. Se non l'hai chiesto tu, ignora questa email.");
    }

    /** Controlla il codice; dopo 5 errori il codice viene bruciato. */
    private function consumeCode(array $user, string $purpose, string $code): void
    {
        $row = $this->codes->findValid((int) $user['id'], $purpose) ?? throw new ValidationException(self::BAD_CODE);

        if (!hash_equals($row['code'], trim($code))) { // hash_equals: confronto a tempo costante
            if ($this->codes->addAttempt((int) $row['id']) >= self::MAX_CODE_ATTEMPTS) {
                $this->codes->delete((int) $row['id']);
            }
            throw new ValidationException('Codice errato');
        }
        $this->codes->delete((int) $row['id']);
    }

    private static function email(mixed $email, bool $strict = true): string
    {
        $email = mb_strtolower(trim((string) $email));
        if ($strict && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Email non valida');
        }

        return $email;
    }

    private static function password(mixed $password): string
    {
        $password = (string) $password;
        if (mb_strlen($password) < 8 || mb_strlen($password) > 200) {
            throw new ValidationException('La password deve avere almeno 8 caratteri');
        }

        return $password;
    }

    private static function publicUser(array $user): array
    {
        return ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email']];
    }
}
