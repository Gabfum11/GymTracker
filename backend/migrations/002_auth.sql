-- Autenticazione "come TrackIt" (spec §6.3): JWT + refresh token, verifica email, limiti anti-abuso.

ALTER TABLE users
    ADD COLUMN is_verified   BOOLEAN NOT NULL DEFAULT false,
    -- incrementandolo, tutti gli access token già emessi smettono di valere (logout ovunque, furto, cambio password)
    ADD COLUMN token_version INT     NOT NULL DEFAULT 0;

-- la prima versione usava token opachi: sostituiti da access JWT + refresh token
DROP TABLE auth_tokens;

CREATE TABLE refresh_tokens (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users ON DELETE CASCADE,
    token_hash CHAR(64) NOT NULL UNIQUE,  -- SHA-256: il token in chiaro lo ha solo il telefono
    expires_at TIMESTAMPTZ NOT NULL,
    used_at    TIMESTAMPTZ NULL,          -- valorizzato quando viene scambiato: se torna indietro, qualcuno l'ha copiato
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- codici a 6 cifre mandati via email: verifica dell'indirizzo e recupero password
CREATE TABLE otp_codes (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users ON DELETE CASCADE,
    purpose    TEXT NOT NULL CHECK (purpose IN ('verify', 'reset')),
    code       CHAR(6) NOT NULL,
    attempts   SMALLINT NOT NULL DEFAULT 0,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (user_id, purpose)             -- un solo codice valido alla volta per scopo
);

-- contatori per i limiti: "login:10.0.0.1", "parse:7", "otp:7"...
CREATE TABLE rate_limits (
    bucket   TEXT PRIMARY KEY,
    count    INT NOT NULL,
    reset_at TIMESTAMPTZ NOT NULL
);
