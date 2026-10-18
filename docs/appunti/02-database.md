# 02 · Il database: tabelle e migrazioni

## Cosa fa

- `migrations/001_init.sql` descrive le **5 tabelle** dell'app.
- `src/Migrator.php` applica i file `.sql` che il database non ha ancora visto e se li annota nella tabella `schema_migrations`.
- `bin/migrate.php` è il comando da lanciare: `php bin/migrate.php`.
- `src/Database.php` apre la connessione a PostgreSQL con **PDO**, leggendo i dati di accesso da `.env`.
- `tests/Support/DbTestCase.php` è la base per i test che usano il database: prima di ogni test applica le migrazioni al database `gymtracker_test` e lo svuota.

## Le tabelle

```
users ──< auth_tokens          un utente ha tanti token (uno per dispositivo)
  │
  ├──< exercises               un utente ha i suoi esercizi ("Dip zavorrati", "Panca piana")
  │        │
  └──< workouts                un utente ha tanti allenamenti
           │
           └──< sets >──── exercises     una serie appartiene a un allenamento E a un esercizio
```

`──<` si legge "uno a molti". `sets` è la tabella che collega tutto: "nell'allenamento 12, all'esercizio Dip, la 2ª serie era 45 kg × 6".

| Tabella | Colonne importanti |
|---|---|
| `users` | `email` unica, `password_hash` (mai la password in chiaro) |
| `auth_tokens` | `token_hash`: anche il token di login lo salviamo solo come hash |
| `exercises` | `name`, `is_weighted_bodyweight` (esercizio a zavorra: il peso si mostra col "+") |
| `workouts` | `started_at`, `ended_at` (vuoto = allenamento in corso) |
| `sets` | `position` (1ª, 2ª…), `weight_kg`, `reps` |

**Cosa NON salviamo:** record, titolo dell'allenamento, durata, calendario. Si **calcolano** dai dati: se salvassimo anche il record, rischieremmo che non coincida con le serie vere.

## Le regole dentro il database (vincoli)

Il database stesso rifiuta i dati sbagliati, anche se un giorno il PHP avesse un bug:

- `REFERENCES users ON DELETE CASCADE`: una serie deve appartenere a un allenamento esistente; se cancelli un utente, spariscono anche tutti i suoi dati.
- `CHECK (weight_kg BETWEEN 0 AND 500)` e `CHECK (reps BETWEEN 1 AND 100)`: niente 1000 kg o 0 ripetizioni.
- `UNIQUE INDEX ... (user_id, lower(name))`: "Dip" e "dip" non possono essere due esercizi diversi dello stesso utente.
- `UNIQUE INDEX ... (user_id) WHERE ended_at IS NULL`: **un solo allenamento aperto per utente**. È un "indice parziale": vale solo per le righe con `ended_at` vuoto.
- `NUMERIC(5,2)` per i kg: numero decimale **esatto** (42,50). Con `FLOAT`, 42,5 potrebbe diventare 42,4999999.

## PDO in due righe

PDO è il modo standard di PHP per parlare con un database (incluso in PHP, niente librerie).

```php
$stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
$stmt->execute(['Mario', 'mario@test.it', '...']);
```

I `?` sono **segnaposto**: i valori viaggiano separati dalla query, così un utente che scrive `'; DROP TABLE users; --` come nome non può modificare la query (si chiama **SQL injection**). Questa è la regola: mai mettere un valore dentro la stringa SQL con il `.`.

Le due opzioni in `Database::connect`:
- `ERRMODE_EXCEPTION`: se una query fallisce, PHP lancia un'eccezione (altrimenti restituirebbe `false` in silenzio).
- `FETCH_ASSOC`: ogni riga arriva come array `['name' => 'Mario', ...]`.

## Le migrazioni

Una migrazione è un file che porta il database da una versione alla successiva. Il `Migrator`:
1. crea `schema_migrations` se non esiste;
2. legge quali file ha già applicato;
3. applica gli altri **in ordine** (`001_`, `002_`…), ognuno in una **transazione**: o va tutto a buon fine, o non cambia niente.

Per aggiungere una colonna più avanti **non si modifica** `001_init.sql`: si crea `002_...sql`. Così il database online, che ha già il 001, riceve solo la novità.

## I test

- `test_all_tables_exist`: le 6 tabelle esistono.
- `test_only_one_open_workout_per_user`: un secondo allenamento aperto viene rifiutato con l'errore `23505` (violazione di un vincolo `UNIQUE`).
- `test_migrations_are_applied_only_once`: rilanciare le migrazioni non applica niente.

Si chiamano test **di integrazione** (cartella `Integration`) perché usano un pezzo esterno vero, il database, a differenza dei test **unitari** (`Unit`).

⚠️ Per i test di integrazione **Docker deve essere acceso** (`docker compose up -d`). Se vedi "Connection refused", è quello.

## Come in TrackIt

| GymTracker | TrackIt |
|---|---|
| `migrations/001_init.sql` (SQL scritto a mano) | `alembic/versions/*.py` (generati dai modelli) |
| `php bin/migrate.php` | `alembic upgrade head` |
| `schema_migrations` | `alembic_version` |
| `Database::connect()` + PDO | `create_engine()` + SQLAlchemy in `database.py` |
| `$stmt->execute([...])` con `?` | query di SQLAlchemy, che usa i segnaposto per te |

## In Laravel sarebbe…

- Le migrazioni sono classi PHP in `database/migrations/`, con `Schema::create('workouts', function (Blueprint $table) { ... })`, e si lanciano con `php artisan migrate`.
- La connessione si configura in `.env` (`DB_CONNECTION=pgsql`, `DB_HOST`…) e Laravel la apre da solo.
- Le query si scrivono con **Eloquent** (`Workout::where('user_id', $id)->get()`) o con `DB::select('... ?', [$id])`, che sotto usa sempre PDO.
- I test usano il trait `RefreshDatabase`, che fa quello che fa il nostro `DbTestCase`.

## Domande da colloquio

1. **Cos'è una SQL injection e come la eviti?** È quando un input dell'utente diventa parte della query SQL. La eviti con i prepared statement: query e valori viaggiano separati.
2. **Perché `NUMERIC` e non `FLOAT` per i kg?** `FLOAT` è approssimato (42,5 può diventare 42,4999…); `NUMERIC` è esatto. Per pesi e soldi serve esatto.
3. **Perché i vincoli nel database se validi già in PHP?** Difesa in profondità: il database è l'ultima barriera e protegge i dati anche da bug, script o modifiche a mano.

## Prova per te

Aggiungi una colonna `notes` (testo, facoltativo) alla tabella `sets`:
1. crea `migrations/002_set_notes.sql` con `ALTER TABLE sets ADD COLUMN notes TEXT NULL;`
2. lancia `php bin/migrate.php` → deve dire `applicata 002_set_notes.sql`;
3. lancialo di nuovo → `niente da applicare`;
4. controlla con `docker compose exec db psql -U gym -d gymtracker -c "\d sets"`.

Poi **cancella il file 002** (non ci serve nell'app). Attenzione: il database ormai ha la colonna; per tornare indietro ricrea il database con `docker compose down -v && docker compose up -d` e poi `php bin/migrate.php`.
