<?php

declare(strict_types=1);

namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;

/**
 * Crea e verifica i JWT (come security.py in TrackIt).
 * Un JWT è un testo firmato con il segreto del server: chi non conosce il segreto non può crearne uno valido,
 * e chi lo modifica rompe la firma.
 */
final class TokenService
{
    private const ALGORITHM = 'HS256';
    public const ACCESS_TTL = 15 * 60; // 15 minuti, come TrackIt

    public function __construct(private string $secret)
    {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('JWT_SECRET deve essere lungo almeno 32 caratteri');
        }
    }

    /**
     * @param int $version token_version dell'utente: se cambia, il token non vale più
     * @param string|null $purpose null = accesso normale; "password_reset" = solo per cambiare password
     */
    public function accessToken(int $userId, int $version, ?string $purpose = null, int $ttl = self::ACCESS_TTL): string
    {
        $now = time();
        $payload = ['sub' => (string) $userId, 'ver' => $version, 'iat' => $now, 'exp' => $now + $ttl];
        if ($purpose !== null) {
            $payload['purpose'] = $purpose;
        }

        return JWT::encode($payload, $this->secret, self::ALGORITHM);
    }

    /** Il contenuto del token, oppure null se la firma è sbagliata, è scaduto o non è un JWT. */
    public function decode(string $jwt): ?array
    {
        try {
            return (array) JWT::decode($jwt, new Key($this->secret, self::ALGORITHM));
        } catch (\Throwable) {
            return null;
        }
    }
}
