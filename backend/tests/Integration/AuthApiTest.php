<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\AppFactory;
use App\Service\TokenService;
use Slim\App;
use Tests\Support\DbTestCase;
use Tests\Support\FakeMailer;

final class AuthApiTest extends DbTestCase
{
    private App $app;
    private FakeMailer $mailer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mailer = new FakeMailer();
        $this->app = AppFactory::create(['jwt_secret' => self::JWT_SECRET], $this->pdo, null, $this->mailer);
    }

    private function call(string $method, string $path, ?array $body = null, ?string $token = null, string $ip = '10.0.0.1'): array
    {
        return $this->request($this->app, $method, $path, $body, $token, $ip);
    }

    /** Registra e verifica un utente; restituisce la sessione (access + refresh token). */
    private function registeredUser(string $email = 'mario@test.it', string $password = 'password123'): array
    {
        $this->call('POST', '/auth/register', ['name' => 'Mario', 'email' => $email, 'password' => $password]);
        [, $session] = $this->call('POST', '/auth/verify-email', ['email' => $email, 'code' => $this->mailer->lastCode()]);

        return $session;
    }

    public function test_register_sends_a_code_and_login_needs_verification(): void
    {
        [$status] = $this->call('POST', '/auth/register', ['name' => 'Mario', 'email' => 'Mario@Test.it ', 'password' => 'password123']);

        $this->assertSame(201, $status);
        $this->assertSame('mario@test.it', $this->mailer->sent[0]['to']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->mailer->lastCode());

        [$status, $body] = $this->call('POST', '/auth/login', ['email' => 'mario@test.it', 'password' => 'password123']);
        $this->assertSame(403, $status);
        $this->assertSame('Verifica prima la tua email', $body['error']);
    }

    public function test_verify_email_returns_tokens_and_me_works(): void
    {
        $session = $this->registeredUser();

        $this->assertNotEmpty($session['access_token']);
        $this->assertSame(64, strlen($session['refresh_token']));
        [$status, $me] = $this->call('GET', '/me', null, $session['access_token']);
        $this->assertSame(200, $status);
        $this->assertSame(['id' => 1, 'name' => 'Mario', 'email' => 'mario@test.it'], $me);
    }

    public function test_login_after_verification(): void
    {
        $this->registeredUser();

        [$status, $session] = $this->call('POST', '/auth/login', ['email' => 'MARIO@test.it', 'password' => 'password123']);

        $this->assertSame(200, $status);
        $this->assertSame('Mario', $session['user']['name']);
    }

    public function test_wrong_code_five_times_burns_the_code(): void
    {
        $this->call('POST', '/auth/register', ['name' => 'Mario', 'email' => 'mario@test.it', 'password' => 'password123']);
        $right = $this->mailer->lastCode();
        $wrong = $right === '111111' ? '222222' : '111111';

        for ($i = 0; $i < 5; $i++) {
            [$status] = $this->call('POST', '/auth/verify-email', ['email' => 'mario@test.it', 'code' => $wrong]);
            $this->assertSame(400, $status);
        }
        [$status, $body] = $this->call('POST', '/auth/verify-email', ['email' => 'mario@test.it', 'code' => $right]);

        $this->assertSame(400, $status);
        $this->assertSame('Codice scaduto o già usato: chiedine uno nuovo', $body['error']);
    }

    public function test_resend_code_sends_a_new_code(): void
    {
        $this->call('POST', '/auth/register', ['name' => 'Mario', 'email' => 'mario@test.it', 'password' => 'password123']);

        [$status] = $this->call('POST', '/auth/resend-code', ['email' => 'mario@test.it']);

        $this->assertSame(204, $status);
        $this->assertCount(2, $this->mailer->sent);
    }

    public function test_codes_are_limited_to_three_per_hour(): void
    {
        $this->call('POST', '/auth/register', ['name' => 'Mario', 'email' => 'mario@test.it', 'password' => 'password123']);
        $this->call('POST', '/auth/resend-code', ['email' => 'mario@test.it']);
        $this->call('POST', '/auth/resend-code', ['email' => 'mario@test.it']);

        [$status] = $this->call('POST', '/auth/resend-code', ['email' => 'mario@test.it']);

        $this->assertSame(429, $status);
        $this->assertCount(3, $this->mailer->sent);
    }

    public function test_duplicate_verified_email_409(): void
    {
        $this->registeredUser();

        [$status] = $this->call('POST', '/auth/register', ['name' => 'Altro', 'email' => 'mario@test.it', 'password' => 'password456']);

        $this->assertSame(409, $status);
    }

    public function test_reregistering_an_unverified_email_replaces_the_account(): void
    {
        $this->call('POST', '/auth/register', ['name' => 'Mario', 'email' => 'mario@test.it', 'password' => 'vecchia123']);
        $this->call('POST', '/auth/register', ['name' => 'Mario', 'email' => 'mario@test.it', 'password' => 'nuova12345']);
        $this->call('POST', '/auth/verify-email', ['email' => 'mario@test.it', 'code' => $this->mailer->lastCode()]);

        [$status] = $this->call('POST', '/auth/login', ['email' => 'mario@test.it', 'password' => 'nuova12345']);

        $this->assertSame(200, $status);
        $this->assertSame(1, (int) $this->pdo->query('SELECT count(*) FROM users')->fetchColumn());
    }

    public function test_invalid_registration_data_400(): void
    {
        [$short] = $this->call('POST', '/auth/register', ['name' => 'Mario', 'email' => 'mario@test.it', 'password' => 'corta']);
        [$email] = $this->call('POST', '/auth/register', ['name' => 'Mario', 'email' => 'non-una-email', 'password' => 'password123']);
        [$name] = $this->call('POST', '/auth/register', ['name' => ' ', 'email' => 'mario@test.it', 'password' => 'password123']);

        $this->assertSame([400, 400, 400], [$short, $email, $name]);
    }

    public function test_wrong_password_and_unknown_email_get_the_same_answer(): void
    {
        $this->registeredUser();

        $wrongPassword = $this->call('POST', '/auth/login', ['email' => 'mario@test.it', 'password' => 'sbagliata1']);
        $unknownEmail = $this->call('POST', '/auth/login', ['email' => 'nessuno@test.it', 'password' => 'password123']);

        $this->assertSame([401, ['error' => 'Email o password errati']], $wrongPassword);
        $this->assertSame($wrongPassword, $unknownEmail);
    }

    public function test_password_is_stored_with_argon2(): void
    {
        $this->registeredUser();

        $hash = $this->pdo->query('SELECT password_hash FROM users')->fetchColumn();

        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    public function test_protected_route_needs_a_valid_token(): void
    {
        $this->assertSame(401, $this->call('GET', '/me')[0]);
        $this->assertSame(401, $this->call('GET', '/me', null, 'non-un-jwt')[0]);
        $forged = (new TokenService('un-altro-segreto-lungo-almeno-32-caratteri'))->accessToken(1, 0);
        $this->assertSame(401, $this->call('GET', '/me', null, $forged)[0]);
    }

    public function test_expired_access_token_401(): void
    {
        $this->registeredUser();
        $expired = (new TokenService(self::JWT_SECRET))->accessToken(1, 0, null, -10);

        $this->assertSame(401, $this->call('GET', '/me', null, $expired)[0]);
    }

    public function test_refresh_rotates_and_the_old_refresh_stops_working(): void
    {
        $session = $this->registeredUser();

        [$status, $new] = $this->call('POST', '/auth/refresh', ['refresh_token' => $session['refresh_token']]);

        $this->assertSame(200, $status);
        $this->assertNotSame($session['refresh_token'], $new['refresh_token']);
        $this->assertSame(200, $this->call('GET', '/me', null, $new['access_token'])[0]);
        $this->assertSame(200, $this->call('POST', '/auth/refresh', ['refresh_token' => $new['refresh_token']])[0]);
    }

    public function test_reused_refresh_token_closes_every_session(): void
    {
        $session = $this->registeredUser();
        [, $new] = $this->call('POST', '/auth/refresh', ['refresh_token' => $session['refresh_token']]);

        // qualcuno usa il refresh vecchio (copiato): è il segnale di un furto
        [$status] = $this->call('POST', '/auth/refresh', ['refresh_token' => $session['refresh_token']]);

        $this->assertSame(401, $status);
        $this->assertSame(401, $this->call('POST', '/auth/refresh', ['refresh_token' => $new['refresh_token']])[0]);
        $this->assertSame(401, $this->call('GET', '/me', null, $new['access_token'])[0]); // token_version cambiato
    }

    public function test_logout_revokes_the_refresh_token(): void
    {
        $session = $this->registeredUser();

        [$status] = $this->call('POST', '/auth/logout', ['refresh_token' => $session['refresh_token']]);

        $this->assertSame(204, $status);
        $this->assertSame(401, $this->call('POST', '/auth/refresh', ['refresh_token' => $session['refresh_token']])[0]);
    }

    public function test_logout_all_closes_every_session(): void
    {
        $phone = $this->registeredUser();
        [, $laptop] = $this->call('POST', '/auth/login', ['email' => 'mario@test.it', 'password' => 'password123']);

        [$status] = $this->call('POST', '/auth/logout-all', null, $laptop['access_token']);

        $this->assertSame(204, $status);
        $this->assertSame(401, $this->call('POST', '/auth/refresh', ['refresh_token' => $phone['refresh_token']])[0]);
        $this->assertSame(401, $this->call('GET', '/me', null, $phone['access_token'])[0]);
    }

    public function test_refresh_token_is_stored_hashed(): void
    {
        $session = $this->registeredUser();

        $stored = $this->pdo->query('SELECT token_hash FROM refresh_tokens')->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertNotContains($session['refresh_token'], $stored);
        $this->assertContains(hash('sha256', $session['refresh_token']), $stored);
    }

    public function test_login_is_rate_limited_per_ip(): void
    {
        $this->registeredUser();
        for ($i = 0; $i < 10; $i++) {
            $this->call('POST', '/auth/login', ['email' => 'mario@test.it', 'password' => 'sbagliata1'], null, '10.0.0.9');
        }

        [$blocked] = $this->call('POST', '/auth/login', ['email' => 'mario@test.it', 'password' => 'password123'], null, '10.0.0.9');
        [$otherIp] = $this->call('POST', '/auth/login', ['email' => 'mario@test.it', 'password' => 'password123'], null, '10.0.0.7');

        $this->assertSame(429, $blocked);
        $this->assertSame(200, $otherIp);
    }

    public function test_other_users_workout_is_404(): void
    {
        $mario = $this->registeredUser();
        $luca = $this->registeredUser('luca@test.it');
        [, $workout] = $this->call('POST', '/workouts', null, $mario['access_token']);
        [, $exercise] = $this->call('POST', '/exercises', ['name' => 'Dip'], $luca['access_token']);

        [$status] = $this->call('PUT', "/workouts/{$workout['id']}/exercises/{$exercise['id']}/sets",
            ['sets' => [['weight_kg' => 45, 'reps' => 8]]], $luca['access_token']);

        $this->assertSame(404, $status);
    }
}
