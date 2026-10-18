-- Schema iniziale di GymTracker (spec §5.1).
-- Record, titoli, durata e calendario NON si salvano: si calcolano da queste tabelle.

CREATE TABLE users (
    id            SERIAL PRIMARY KEY,
    name          TEXT NOT NULL,
    email         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- token di login: salviamo solo l'hash, mai il token in chiaro
CREATE TABLE auth_tokens (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users ON DELETE CASCADE,
    token_hash CHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- esercizi propri di ogni utente ("Dip zavorrati" di Mario non è quello di Luca)
CREATE TABLE exercises (
    id                     SERIAL PRIMARY KEY,
    user_id                INT NOT NULL REFERENCES users ON DELETE CASCADE,
    name                   TEXT NOT NULL,
    is_weighted_bodyweight BOOLEAN NOT NULL DEFAULT false,
    created_at             TIMESTAMPTZ NOT NULL DEFAULT now()
);
-- "Dip" e "dip" sono lo stesso esercizio per lo stesso utente
CREATE UNIQUE INDEX exercises_user_name ON exercises (user_id, lower(name));

CREATE TABLE workouts (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users ON DELETE CASCADE,
    started_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    ended_at   TIMESTAMPTZ NULL  -- NULL = allenamento ancora in corso
);
-- al massimo un allenamento aperto per utente
CREATE UNIQUE INDEX workouts_one_open ON workouts (user_id) WHERE ended_at IS NULL;

CREATE TABLE sets (
    id          SERIAL PRIMARY KEY,
    workout_id  INT NOT NULL REFERENCES workouts ON DELETE CASCADE,
    exercise_id INT NOT NULL REFERENCES exercises ON DELETE CASCADE,
    position    SMALLINT NOT NULL,  -- 1ª, 2ª, 3ª serie di quell'esercizio
    weight_kg   NUMERIC(5,2) NOT NULL CHECK (weight_kg BETWEEN 0 AND 500),
    reps        SMALLINT NOT NULL CHECK (reps BETWEEN 1 AND 100),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
