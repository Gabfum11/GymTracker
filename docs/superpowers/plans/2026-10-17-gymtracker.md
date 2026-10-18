# GymTracker — piano di implementazione

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Web app (telefono + computer) per registrare gli allenamenti scrivendo in una chat; il bot trasforma il testo in serie (kg × rip) da confermare, con Storico, Progressi e Profilo.

**Architecture:** API REST in PHP 8 + Slim 4 a strati (Controller → Service → Repository → PostgreSQL), con l'AI isolata in `ParserService` dietro un'interfaccia `LlmClient`. Frontend Flutter Web in MVVM leggero (Screen → ViewModel `ChangeNotifier` → `ApiClient`). Deploy: API su Render (Docker), DB su Supabase, frontend su GitHub Pages.

**Tech Stack:** PHP 8.3, Slim 4, slim/psr7, vlucas/phpdotenv, PHPUnit 11, PDO pgsql, PostgreSQL 16 (Docker in locale, Supabase online), Groq API (`openai/gpt-oss-20b`, `response_format: json_object`), Flutter (stable) + `http`, `shared_preferences`, `google_fonts`, `fl_chart`.

**Spec:** `docs/superpowers/specs/2026-10-17-gymtracker-design.md` (mockup: https://claude.ai/artifact/9pcwvcKMuGcWtnfdAmGxu7)

## Global Constraints

- Il codice lo studia un principiante che al colloquio deve spiegarlo: soluzioni da manuale, niente trucchi; ogni file ha una sola responsabilità; commenti solo sul *perché*.
- **Ogni task chiude con** `docs/appunti/NN-<slug>.md` (sezioni: *Cosa fa* · *Perché così* · *Come in TrackIt* (analogia con `D:\FinanceApp`: FastAPI router ↔ Controller, `business_logic` ↔ Service, SQLAlchemy ↔ Repository/PDO, pytest ↔ PHPUnit, React Native ↔ Flutter) · *In Laravel sarebbe…* (lo stesso concetto con i nomi di Laravel: rotte, middleware, Eloquent, Form Request, Sanctum, Artisan — l'utente ha scelto Slim ma deve saper rispondere su Laravel) · *Domande da colloquio* (3) · *Prova per te* (una piccola modifica da fare da solo)). Un commit per task, messaggio in italiano.
- Validazione (spec §5.2): kg 0–500, multipli di 0,25; rip intere 1–100; nome esercizio non vuoto, ≤ 60 caratteri. Vale per input del client **e** output dell'AI.
- SQL solo con prepared statements PDO. Nessun dato salvato senza conferma esplicita dell'utente (la risposta dell'AI non tocca mai il DB).
- Segreti (`GROQ_API_KEY`, password DB) solo in `.env` / variabili d'ambiente; `.env` in `.gitignore`.
- Ogni endpoint tranne `/health`, `/auth/register`, `/auth/login` richiede `Authorization: Bearer <token>` (dal Task 9) e vede solo i dati del proprio utente: una risorsa di un altro utente risponde **404**.
- Parser: max **200 chiamate/giorno per utente** → oltre: HTTP 429.
- Record (spec §5.3): metrica = kg se l'esercizio ha mai avuto una serie con peso > 0, altrimenti ripetizioni; la prima sessione di un esercizio non è record; al massimo un record per esercizio per allenamento (la serie migliore).
- Titolo allenamento = primi due esercizi uniti da `" & "` (uno solo se ce n'è uno).
- Testi UI in italiano. Tema scuro: lime = azioni, arancione = solo record; Barlow Condensed (titoli/numeri) + Barlow (testo); pulsanti ≥ 48 px.
- Numeri nell'UI: virgola decimale ("42,5"), peso a zavorra col "+" ("+45 kg × 8").

## Review Focus

1. **Virgola decimale** ("42,5") scritta nella chat o nei campi della scheda → deve valere 42.5, non 42 né errore. Test in Task 3 (`SetValidator`) e Task 7 (`parseItalianNumber`).
2. **L'AI risponde male** (JSON rotto, kg = 1000, rip = 0, campi mancanti) → il bot fa una domanda ("Non ho capito, puoi riscriverlo? Es. 45 kg × 8"), niente crash e niente dato salvato. Test in Task 5.
3. **Ricarico la pagina a metà allenamento** → ritrovo l'allenamento aperto con le schede già confermate. Test in Task 4 (`current`) e Task 7 (ViewModel che ripristina).
4. **Groq giù, lento o oltre limite** → messaggio chiaro ("Il bot non risponde, riprova tra poco"), la scheda in corso non si perde. Test in Task 5 (eccezione → 503) e Task 9 (429).
5. **Id di un altro utente** in `/workouts/{id}`, `/exercises/{id}/progress` → 404. Test in Task 9.

---

## Calendario indicativo (controllo dell'avanzamento ogni giorno)

| Giorno | Task |
|---|---|
| 1 | 1, 2 |
| 2 | 3, 4 |
| 3 | 5 |
| 4 | 6 |
| 5 | 7 |
| 6 | 8 |
| 7 | 9, 10 |
| 8 | 11 (online!) |
| 9 | 12 |
| 10 | 13 |
| 11 | 14 |
| 12 | 15 |
| 13–14 | 16 + margine |

## Struttura dei file

```
docker-compose.yml            Postgres 16 locale (db gymtracker + gymtracker_test)
docker/init-test-db.sql
backend/
  composer.json  phpunit.xml  .env.example  Dockerfile  .dockerignore
  public/index.php            avvio: legge .env, crea App, run()
  bin/migrate.php             esegue migrations/*.sql non ancora applicate
  bin/seed-demo.php           utente demo + ~8 settimane di allenamenti
  migrations/001_init.sql  002_parse_usage.sql
  src/
    AppFactory.php            create(PDO, LlmClient, array $config): Slim\App — rotte e dipendenze, a mano
    Database.php              connect(array $env): PDO
    Http/  HealthController, ExerciseController, WorkoutController, ParseController,
           AuthController, StatsController, AuthMiddleware, CorsMiddleware, JsonResponse (helper)
    Service/ SetValidator, WorkoutService, SummaryService, RecordService, ParserService,
             AuthService, RateLimiter, ValidationException, NotFoundException
    Ai/      LlmClient (interface), GroqClient, LlmException
    Repository/ UserRepository, TokenRepository, ExerciseRepository, WorkoutRepository,
                SetRepository, ParseUsageRepository
  tests/  Unit/…  Integration/… (DB gymtracker_test)  Support/FakeLlmClient.php  Support/DbTestCase.php
app/                          progetto Flutter
  lib/main.dart  theme.dart  shell.dart
  lib/api/api_client.dart  lib/api/api_exception.dart  lib/util/format.dart
  lib/models/  exercise.dart  set_entry.dart  workout_summary.dart  parse_result.dart  progress.dart  profile.dart
  lib/features/auth/      auth_view_model.dart  login_screen.dart
  lib/features/chat/      chat_view_model.dart  chat_screen.dart  confirm_card.dart
  lib/features/summary/   summary_view.dart  (Riepilogo e foglio dello Storico)
  lib/features/history/   history_view_model.dart  history_screen.dart
  lib/features/progress/  progress_view_model.dart  progress_screen.dart
  lib/features/profile/   profile_view_model.dart  profile_screen.dart  training_calendar.dart
  test/ …
.github/workflows/web.yml
docs/appunti/
README.md
```

## Contratto API (riferimento per tutti i task)

Tutte le risposte sono JSON. Errori: `{"error": "<messaggio in italiano>"}` con 400 (validazione), 401, 404, 409, 429, 503.

| Metodo e percorso | Corpo | Risposta |
|---|---|---|
| `GET /health` | — | `{"ok": true}` |
| `POST /auth/register` | `{name, email, password}` (password ≥ 8) | 201 `{token, user:{id,name,email}}`; 409 email già usata |
| `POST /auth/login` | `{email, password}` | `{token, user}`; 401 |
| `POST /auth/logout` | — | 204 (cancella il token) |
| `GET /me` | — | `{id, name, email}` |
| `GET /exercises` | — | `[{id, name, is_weighted_bodyweight}]` ordinati per nome |
| `POST /exercises` | `{name, is_weighted_bodyweight}` | 201 nuovo, 200 se esiste già (nome uguale ignorando maiuscole/spazi) |
| `POST /workouts` | — | allenamento aperto (lo crea se non c'è): `WorkoutSummary` |
| `GET /workouts/current` | — | `WorkoutSummary` o `null` |
| `PUT /workouts/{id}/exercises/{exerciseId}/sets` | `{sets:[{weight_kg, reps}]}` (lista vuota = toglie l'esercizio) | `WorkoutSummary` |
| `POST /workouts/{id}/finish` | — | `WorkoutSummary` con `ended_at` |
| `GET /workouts` | — | `[WorkoutSummary]` solo chiusi, dal più recente |
| `GET /workouts/{id}` | — | `WorkoutSummary` |
| `POST /parse` | `{text, current_exercise?: string, pending_sets?: [{weight_kg,reps}]}` | `ParseResult` (spec §6.4) |
| `GET /exercises/{id}/progress` | — | `{metric:"kg"\|"reps", best, first, points:[{date, value}]}` |
| `GET /exercises/{id}/last` | — | `{date, sets:[{weight_kg,reps}]}` o `null` |
| `GET /profile` | — | `{name, email, training_days:["2026-10-17",…] (ultimi 91 giorni), recent_records:[RecordOut ×3]}` |

`WorkoutSummary` = `{id, title, started_at, ended_at, duration_minutes, set_count, exercise_count, records:[RecordOut], exercises:[{id, name, is_weighted_bodyweight, sets:[{weight_kg, reps, is_record}]}]}`
`RecordOut` = `{exercise, date, metric, weight_kg, reps}`
`ParseResult` = `{type:"sets", exercise, typed_name, matched_existing_id, weighted, sets:[…]}` | `{type:"exercise", exercise, typed_name, matched_existing_id, weighted}` | `{type:"question", text}` — `weighted` = esercizio a zavorra (spec §4.1 "Peso aggiunto")

---

### Task 1: Ambiente, repository e prima API che risponde

**Files:**
- Create: `.gitignore`, `docker-compose.yml`, `docker/init-test-db.sql`, `backend/composer.json`, `backend/phpunit.xml`, `backend/.env.example`, `backend/public/index.php`, `backend/src/AppFactory.php`, `backend/src/Http/HealthController.php`, `backend/src/Http/JsonResponse.php`, `backend/tests/Unit/HealthTest.php`, `docs/appunti/01-ambiente-e-health.md`

**Interfaces:**
- Produces: `AppFactory::create(PDO $pdo, LlmClient $llm, array $config): \Slim\App` (in questo task i parametri `$pdo`/`$llm` possono essere nullable e venire usati dai task successivi); `JsonResponse::with(ResponseInterface $r, mixed $data, int $status = 200): ResponseInterface`.

- [ ] **Step 1: Installazioni dell'utente** (guidate, con screenshot negli appunti): Composer per Windows (Composer-Setup.exe, punta a `D:\php\php.exe`); in `D:\php\php.ini` abilitare `extension=pdo_pgsql`, `pgsql`, `curl`, `mbstring`, `openssl`, `fileinfo`. Verifica: `composer -V` e `php -m` contiene `pdo_pgsql`.
- [ ] **Step 2: Repository** — `git init`, `.gitignore` con `backend/vendor/`, `backend/.env`, `app/build/`, `app/.dart_tool/`, `.superpowers/`; primo commit con `docs/` (spec, piano).
- [ ] **Step 3: Postgres locale** — `docker-compose.yml`: servizio `db` `postgres:16`, porta `5432`, utente/password `gym`/`gym`, db `gymtracker`; `docker/init-test-db.sql` crea `gymtracker_test`. Verifica: `docker compose up -d` poi `docker compose exec db psql -U gym -c "\l"` mostra i due DB.
- [ ] **Step 4: Composer** — `composer require slim/slim:^4 slim/psr7 vlucas/phpdotenv` e `composer require --dev phpunit/phpunit:^11`; autoload PSR-4 `"App\\": "src/"`, `"Tests\\": "tests/"`; script `"test": "phpunit"`.
- [ ] **Step 5: Test che fallisce** — `tests/Unit/HealthTest::test_health_returns_ok`: crea l'app con `AppFactory::create(...)`, esegue `GET /health` con `ServerRequestFactory`, asserisce status 200, header `Content-Type: application/json` e corpo `{"ok":true}`.
- [ ] **Step 6: Esegui** `composer test` → FAIL (classe mancante).
- [ ] **Step 7: Implementa** `AppFactory::create`, `HealthController::__invoke`, `JsonResponse::with`; `public/index.php` carica `.env` e chiama `run()`. Aggiungi `addErrorMiddleware` che in produzione risponde `{"error":"Errore interno"}`.
- [ ] **Step 8: Esegui** `composer test` → PASS; poi `php -S localhost:8080 -t public` e `curl http://localhost:8080/health` → `{"ok":true}`.
- [ ] **Step 9: Appunti + commit** `feat: prima API PHP con /health`.

### Task 2: Schema del database e migrazioni

**Files:**
- Create: `backend/migrations/001_init.sql`, `backend/bin/migrate.php`, `backend/src/Database.php`, `backend/tests/Support/DbTestCase.php`, `backend/tests/Integration/MigrationTest.php`, `docs/appunti/02-database.md`

**Interfaces:**
- Produces: `Database::connect(array $env): PDO` (legge `DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, DB_SSLMODE`; `ERRMODE_EXCEPTION`, `FETCH_ASSOC`); `DbTestCase` (setUp: migra `gymtracker_test` e svuota le tabelle con `TRUNCATE … RESTART IDENTITY CASCADE`, espone `$this->pdo` e `createUser(string $email = 'a@test.it'): int`).
- Schema (`001_init.sql`), spec §5.1:
  - `users(id SERIAL PK, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, created_at TIMESTAMPTZ DEFAULT now())`
  - `auth_tokens(id SERIAL PK, user_id INT REFERENCES users ON DELETE CASCADE, token_hash CHAR(64) UNIQUE NOT NULL, created_at …)`
  - `exercises(id SERIAL PK, user_id INT REFERENCES users ON DELETE CASCADE, name TEXT NOT NULL, is_weighted_bodyweight BOOLEAN NOT NULL DEFAULT false, created_at …)` + `UNIQUE INDEX ON exercises(user_id, lower(name))`
  - `workouts(id SERIAL PK, user_id INT REFERENCES users ON DELETE CASCADE, started_at TIMESTAMPTZ NOT NULL DEFAULT now(), ended_at TIMESTAMPTZ NULL)` + `UNIQUE INDEX ON workouts(user_id) WHERE ended_at IS NULL` (un solo allenamento aperto)
  - `sets(id SERIAL PK, workout_id INT REFERENCES workouts ON DELETE CASCADE, exercise_id INT REFERENCES exercises ON DELETE CASCADE, position SMALLINT NOT NULL, weight_kg NUMERIC(5,2) NOT NULL CHECK (weight_kg BETWEEN 0 AND 500), reps SMALLINT NOT NULL CHECK (reps BETWEEN 1 AND 100), created_at …)`
  - `schema_migrations(name TEXT PK, applied_at …)`

- [ ] **Step 1: Test che fallisce** — `MigrationTest::test_all_tables_exist` (le 6 tabelle in `information_schema.tables`); `test_only_one_open_workout_per_user` (secondo INSERT aperto per lo stesso utente → `PDOException` con SQLSTATE `23505`).
- [ ] **Step 2: Esegui** `composer test -- --filter MigrationTest` → FAIL.
- [ ] **Step 3: Implementa** `001_init.sql`, `Database::connect`, `bin/migrate.php` (applica in ordine alfabetico i file non presenti in `schema_migrations`, ognuno in una transazione).
- [ ] **Step 4: Esegui** test → PASS; `php bin/migrate.php` sul DB di sviluppo stampa `applicata 001_init.sql`, una seconda volta `niente da applicare`.
- [ ] **Step 5: Appunti + commit** `feat: schema del database e migrazioni`.

### Task 3: Validazione delle serie

**Files:**
- Create: `backend/src/Service/SetValidator.php`, `backend/src/Service/ValidationException.php`, `backend/tests/Unit/SetValidatorTest.php`, `docs/appunti/03-validazione.md`

**Interfaces:**
- Produces: `SetValidator::sets(mixed $raw): array` → `list<array{weight_kg: float, reps: int}>` o lancia `ValidationException` (messaggio italiano); `SetValidator::exerciseName(mixed $raw): string` (trim, spazi multipli → uno, prima lettera maiuscola).

- [ ] **Step 1: Test che fallisce** — `SetValidatorTest` con data provider:
  - validi: `[["weight_kg"=>45,"reps"=>8]]`; `"42,5"` → `42.5`; `"42.5"` → `42.5`; `0` kg ammesso; `500` ammesso; `12.25` ammesso.
  - invalidi (→ `ValidationException`): kg `-1`, `500.5`, `42.3` (non multiplo di 0,25), `"abc"`; rip `0`, `101`, `8.5`; lista non-array; lista vuota per `sets()`; più di 20 serie.
  - nomi: `"  dip   zavorrati "` → `"Dip zavorrati"`; `""` e 61 caratteri → eccezione.
- [ ] **Step 2: Esegui** → FAIL.
- [ ] **Step 3: Implementa** (virgola → punto prima di `is_numeric`; multiplo di 0,25 con `fmod($kg * 4, 1) == 0`).
- [ ] **Step 4: Esegui** → PASS.
- [ ] **Step 5: Appunti + commit** `feat: validazione di serie ed esercizi`.

### Task 4: Esercizi e allenamenti (repository, service, API) con utente fisso

**Files:**
- Create: `backend/src/Repository/{ExerciseRepository,WorkoutRepository,SetRepository}.php`, `backend/src/Service/{WorkoutService,SummaryService,NotFoundException}.php`, `backend/src/Http/{ExerciseController,WorkoutController}.php`, `backend/tests/Integration/WorkoutApiTest.php`, `docs/appunti/04-allenamenti.md`
- Modify: `backend/src/AppFactory.php` (rotte), `backend/bin/migrate.php` (opzione `--dev-user`: crea l'utente di sviluppo se manca e ne stampa l'id)

**Interfaces:**
- Consumes: `SetValidator`, `DbTestCase`.
- Produces:
  - `ExerciseRepository::listForUser(int $userId): array`, `findOrCreate(int $userId, string $name, bool $weighted): array{exercise: array, created: bool}`, `find(int $userId, int $id): ?array`
  - `WorkoutRepository::findOpen(int $userId): ?array`, `create(int $userId): array`, `find(int $userId, int $id): ?array`, `finish(int $id): void`, `listClosed(int $userId): array`
  - `SetRepository::replaceForExercise(int $workoutId, int $exerciseId, array $sets): void` (DELETE + INSERT in transazione, `position` 1..n), `forWorkout(int $workoutId): array` (join con il nome esercizio, ordinate per primo inserimento dell'esercizio poi `position`)
  - `WorkoutService::start(int $userId): array`, `current(int $userId): ?array`, `saveSets(int $userId, int $workoutId, int $exerciseId, mixed $rawSets): array`, `finish(int $userId, int $workoutId): array` — restituiscono `WorkoutSummary`; id non dell'utente → `NotFoundException`
  - `SummaryService::build(array $workout, array $sets, array $records = []): array` (WorkoutSummary; `records` vuoto fino al Task 8; `duration_minutes` = da `started_at` a `ended_at` o adesso)
  - Fino al Task 9 l'utente è `$config['dev_user_id']` (creato da `bin/migrate.php --dev-user` se manca).
  - Il `WorkoutController` traduce `ValidationException` → 400, `NotFoundException` → 404.

- [ ] **Step 1: Test che falliscono** (`WorkoutApiTest`, chiamate HTTP sull'app con DB di test):
  - `test_start_is_idempotent`: due `POST /workouts` → stesso `id`.
  - `test_current_restores_open_workout`: start + PUT sets → `GET /workouts/current` contiene l'esercizio con le serie in ordine.
  - `test_current_is_null_without_open_workout`.
  - `test_put_sets_replaces_not_appends`: PUT 3 serie poi PUT 2 serie stesso esercizio → 2 serie; `set_count` 2.
  - `test_put_empty_list_removes_exercise`.
  - `test_invalid_sets_return_400` (rip 0) e il DB resta invariato.
  - `test_finish_sets_ended_at_and_current_becomes_null`; `PUT` su allenamento chiuso → 409.
  - `test_title_uses_first_two_exercises`: Dip, Panca, Curl → `"Dip & Panca"`.
  - `test_post_exercise_reuses_existing_case_insensitive`: `"dip"` dopo `"Dip"` → 200 e stesso id.
- [ ] **Step 2: Esegui** → FAIL.
- [ ] **Step 3: Implementa** repository, service, controller, rotte (tabella "Contratto API").
- [ ] **Step 4: Esegui** → PASS. Prova manuale con `curl` documentata negli appunti.
- [ ] **Step 5: Appunti + commit** `feat: API di esercizi e allenamenti`.

### Task 5: Il parser AI (Groq)

**Files:**
- Create: `backend/src/Ai/{LlmClient,GroqClient,LlmException}.php`, `backend/src/Service/ParserService.php`, `backend/src/Http/ParseController.php`, `backend/tests/Support/FakeLlmClient.php`, `backend/tests/Unit/ParserServiceTest.php`, `backend/tests/Integration/ParseApiTest.php`, `docs/appunti/05-parser-ai.md`
- Modify: `backend/src/AppFactory.php`, `backend/.env.example` (`GROQ_API_KEY`, `GROQ_MODEL=openai/gpt-oss-20b`)

**Interfaces:**
- Consumes: `SetValidator`, `ExerciseRepository::listForUser`.
- Produces:
  - `interface LlmClient { public function completeJson(string $system, string $user): string; }` (lancia `LlmException` su errore di rete/HTTP ≥ 400/timeout 15 s)
  - `GroqClient(string $apiKey, string $model)` — `POST https://api.groq.com/openai/v1/chat/completions` con `curl`, `response_format: {"type":"json_object"}`, `temperature: 0`
  - `ParserService::parse(string $text, array $existingExercises, ?string $currentExercise, ?array $pendingSets): array` → `ParseResult`
  - `FakeLlmClient(string|LlmException $answer)` registra l'ultimo prompt in `$lastSystem`/`$lastUser`.
- Prompt di sistema (in italiano): descrive i tre esiti di spec §6.4, include l'elenco `id: nome` degli esercizi dell'utente, l'esercizio corrente e le serie già nella scheda; regole: peso a corpo libero = 0; "+10" = 10 e `weighted: true`; "3 serie da 8 con 45" = tre serie uguali; se non è chiaro cosa sia kg e cosa rip → `question`; `matched_existing_id` solo da quell'elenco. Correzioni ("la seconda erano 7") → `sets` con la lista **completa** aggiornata.

- [ ] **Step 0: Verifica il modello** — `curl https://api.groq.com/openai/v1/models -H "Authorization: Bearer $GROQ_API_KEY"` contiene `openai/gpt-oss-20b`; se no, scegline uno con JSON mode e annotalo negli appunti.
- [ ] **Step 1: Test che falliscono** (`ParserServiceTest`, con `FakeLlmClient`):
  - `test_valid_sets_answer_is_normalized`: AI `{"type":"sets","exercise":"dip zavorrati","matched_existing_id":null,"sets":[{"weight_kg":"45","reps":8}]}` → `sets[0] == ["weight_kg"=>45.0,"reps"=>8]`, `exercise == "Dip zavorrati"`, `typed_name` = nome scritto.
  - `test_out_of_range_answer_becomes_question`: kg 1000 → `type == "question"`, testo contiene `"Es. 45 kg × 8"`.
  - `test_broken_json_becomes_question`; `test_unknown_type_becomes_question`.
  - `test_unknown_matched_id_is_dropped`: id 999 non nell'elenco → `matched_existing_id === null`.
  - `test_prompt_contains_existing_exercises_and_pending_sets`: `$fake->lastSystem` contiene `"12: Dip zavorrati"` e le serie in sospeso.
  - `test_llm_failure_is_rethrown`: `FakeLlmClient(new LlmException)` → `LlmException`.
  - In `ParseApiTest`: `test_parse_returns_503_when_llm_fails` con `{"error":"Il bot non risponde, riprova tra poco"}`; `test_empty_text_returns_400`.
- [ ] **Step 2: Esegui** → FAIL.
- [ ] **Step 3: Implementa** (decode → controlla `type` → per `sets` passa da `SetValidator::sets`, per i nomi `SetValidator::exerciseName`; qualunque eccezione di validazione → `question`).
- [ ] **Step 4: Esegui** → PASS. Prova reale con la chiave: 5 frasi d'esempio della spec §2 → output annotati negli appunti.
- [ ] **Step 5: Appunti + commit** `feat: parser AI con Groq`.

### Task 6: Progetto Flutter, tema e navigazione

**Files:**
- Create: `app/` (con `flutter create --platforms=web,android app`), `app/lib/{main,theme,shell}.dart`, `app/lib/api/{api_client,api_exception}.dart`, `app/lib/util/format.dart`, `app/test/format_test.dart`, `docs/appunti/06-flutter-base.md`
- Modify: `backend/src/Http/CorsMiddleware.php` (create) + `AppFactory` (origine da `CORS_ORIGIN`, in locale `*`)

**Interfaces:**
- Produces:
  - `theme.dart`: costanti `AppColors.bg, screen, surface, surface2, rule, ink, muted, faint, lime, limeSoft, onLime, record, recordSoft, onRecord` (valori convertiti dai token OKLCH del mockup) e `ThemeData buildTheme()` con `GoogleFonts.barlow` e `GoogleFonts.barlowCondensed` per i titoli.
  - `ApiClient({required String baseUrl, String? token})` con `Future<dynamic> get(String path)`, `post(String path, [Object? body])`, `put(...)`; errori → `ApiException(int status, String message)`; `baseUrl` da `const String.fromEnvironment('API_URL', defaultValue: 'http://localhost:8080')`.
  - `format.dart`: `String formatKg(num kg)` (virgola, niente ",0": `42.5→"42,5"`, `45→"45"`), `String formatSet(num kg, int reps, {bool weighted = false})` (`"+45 kg × 8"`), `double? parseItalianNumber(String s)`.
  - `Shell`: `NavigationBar` a 4 voci **Allena · Storico · Progressi · Profilo** (indicatore lime), schermate segnaposto.
- [ ] **Step 1: Installazione dell'utente**: Flutter SDK stable (zip in `D:\flutter`, aggiunto al PATH), `flutter doctor` senza errori per Chrome.
- [ ] **Step 2: Test che fallisce** `format_test.dart`: `formatKg(42.5) == "42,5"`, `formatKg(45) == "45"`, `formatSet(45, 8, weighted: true) == "+45 kg × 8"`, `parseItalianNumber("42,5") == 42.5`, `parseItalianNumber("x") == null`.
- [ ] **Step 3: Esegui** `flutter test` → FAIL.
- [ ] **Step 4: Implementa** tema, format, api client, shell; la tab Allena mostra il risultato di `GET /health`.
- [ ] **Step 5: Esegui** `flutter test` → PASS; `flutter run -d chrome` con l'API accesa mostra la barra in basso e "API ok".
- [ ] **Step 6: Appunti + commit** `feat: app Flutter con tema e navigazione`.

### Task 7: La chat che registra le serie

**Files:**
- Create: `app/lib/models/{exercise,set_entry,workout_summary,parse_result}.dart`, `app/lib/features/chat/{chat_view_model,chat_screen,confirm_card}.dart`, `app/test/chat_view_model_test.dart`, `app/test/fake_api.dart`, `docs/appunti/07-chat.md`

**Interfaces:**
- Consumes: `ApiClient`, endpoint Task 4–5, `format.dart`.
- Produces:
  - Modelli con `fromJson` (`WorkoutSummary`, `SetEntry{double weightKg; int reps; bool isRecord}`, `ParseResult` sealed-like con `type`).
  - `ChatViewModel extends ChangeNotifier`: stato `List<ChatItem> items` (`BotMessage`, `UserMessage`, `CardItem{String exercise; int? exerciseId; String typedName; bool weighted; List<SetEntry> sets; bool saved}`), `bool hasOpenWorkout`, `bool busy`, `String? error`. Metodi: `Future<void> load()` (ripristina da `/workouts/current`: una `CardItem` salvata per esercizio), `startWorkout()`, `nextExercise()`, `send(String text)`, `updateSet(CardItem, int index, {String? kg, String? reps})`, `addSet(CardItem)` (copia l'ultima), `removeSet(CardItem, int index)`, `confirm(CardItem)` (POST `/exercises` se `exerciseId` nullo, poi PUT sets; se l'esercizio è già nell'allenamento la scheda parte dalle serie esistenti + le nuove), `reopen(CardItem)`, `useTypedName(CardItem)` ("Non è questo esercizio" → nuovo esercizio con `typedName`).
  - Testi del bot: "Che esercizio stai facendo?", "Scrivi le serie come ti viene, anche tutte insieme.", "N serie salvate".
- [ ] **Step 1: Test che falliscono** (`chat_view_model_test.dart` con `FakeApi implements ApiClient`):
  - `load restores saved cards of the open workout` (Review Focus 3).
  - `send with sets answer adds an editable card` e niente chiamate PUT finché non si conferma.
  - `send with question answer adds a bot message only`.
  - `updateSet accepts italian comma` → `42,5` diventa `42.5` (Review Focus 1).
  - `addSet copies the last set`; `removeSet renumbers`.
  - `confirm creates exercise when missing then puts sets` (ordine delle chiamate).
  - `api error keeps the card and sets error` (Review Focus 4).
- [ ] **Step 2: Esegui** → FAIL.
- [ ] **Step 3: Implementa** ViewModel, poi `ChatScreen` (lista + dock con *Prossimo esercizio*/*Fine allenamento*/campo testo, come nel mockup) e `ConfirmCard` (campi `TextField` con `keyboardType: TextInputType.numberWithOptions(decimal: true)`, × per riga, *+ Serie*, *Conferma*, dopo il salvataggio "N serie salvate · Modifica", link "Non è questo esercizio" quando `matched_existing_id` ≠ null e nome ≠ scritto).
- [ ] **Step 4: Esegui** test → PASS; prova manuale nel browser: le 5 frasi della spec §2, modifica di un numero, ricarica della pagina a metà.
- [ ] **Step 5: Appunti + commit** `feat: chat che registra le serie`.

### Task 8: Record e Riepilogo di fine allenamento

**Files:**
- Create: `backend/src/Service/RecordService.php`, `backend/tests/Unit/RecordServiceTest.php`, `app/lib/features/summary/summary_view.dart`, `docs/appunti/08-record-e-riepilogo.md`
- Modify: `WorkoutService`/`SummaryService` (passano i record), `chat_screen.dart` (Fine allenamento → Riepilogo)

**Interfaces:**
- Produces: `RecordService::compute(array $history): array` — input: serie di **un utente**, ognuna `{workout_id, started_at, exercise_id, exercise, weight_kg, reps}`, ordinate per `started_at`; output: lista `RecordOut` con `workout_id`, `exercise_id`, `set_index` (la serie record dentro quell'allenamento). Regole: vedi Global Constraints. `SetRepository::historyForUser(int $userId): array` fornisce l'input. `SummaryView(WorkoutSummary summary)` widget riusato dal foglio dello Storico.
- [ ] **Step 1: Test che falliscono** (`RecordServiceTest`, dati in memoria):
  - `test_first_session_is_not_a_record`.
  - `test_heavier_session_is_one_record_even_with_two_better_sets` (40 → poi 42,5 e 45 → un record, 45).
  - `test_equal_weight_is_not_a_record`.
  - `test_bodyweight_exercise_uses_reps` (sempre 0 kg: 8 → 10 rip = record, metric `reps`).
  - `test_switches_to_kg_once_weight_appears`.
  - `test_records_are_per_exercise` (due esercizi nello stesso allenamento → 2 record).
- [ ] **Step 2: Esegui** → FAIL.
- [ ] **Step 3: Implementa** `RecordService`; `SummaryService` marca `is_record` e riempie `records`; in Flutter `SummaryView` (3 riquadri durata/serie/esercizi, box arancione record, esercizi con etichette serie, record in arancione) e la schermata Riepilogo con *Torna all'allenamento* / *Salva allenamento* (→ `POST /finish`, poi chat torna a "Inizia allenamento").
- [ ] **Step 4: Esegui** `composer test` e `flutter test` → PASS; prova manuale di un allenamento completo.
- [ ] **Step 5: Appunti + commit** `feat: record e riepilogo di fine allenamento`.

### Task 9: Autenticazione a token e limite del parser (backend)

**Files:**
- Create: `backend/src/Repository/{UserRepository,TokenRepository,ParseUsageRepository}.php`, `backend/src/Service/{AuthService,RateLimiter}.php`, `backend/src/Http/{AuthController,AuthMiddleware}.php`, `backend/migrations/002_parse_usage.sql` (`parse_usage(user_id, day DATE, count INT, PK(user_id, day))`), `backend/tests/Integration/AuthApiTest.php`, `docs/appunti/09-autenticazione.md`
- Modify: `AppFactory` (gruppo di rotte protetto), tutti i controller leggono `$request->getAttribute('user_id')` al posto di `dev_user_id`.

**Interfaces:**
- Produces: `AuthService::register(string $name, string $email, string $password): array{token, user}`, `login(string $email, string $password): array{token, user}`, `userIdForToken(string $token): ?int`, `logout(string $token): void`. Token = `bin2hex(random_bytes(32))`, in DB solo `hash('sha256', $token)`. `RateLimiter::hit(int $userId, int $limit = 200): bool` (UPSERT atomico, `false` oltre il limite).
- [ ] **Step 1: Test che falliscono** (`AuthApiTest`):
  - `test_register_then_me`; `test_duplicate_email_409` (maiuscole ignorate); `test_short_password_400`.
  - `test_wrong_password_401` con messaggio uguale a email inesistente.
  - `test_protected_route_without_token_401`; `test_logout_revokes_token`.
  - `test_other_users_workout_is_404` su `GET /workouts/{id}`, `PUT …/sets`, `GET /exercises/{id}/progress` (Review Focus 5; la rotta progress arriva al Task 13: aggiungere lì l'asserzione).
  - `test_parse_over_limit_returns_429` (limite abbassato a 2 via config).
  - `test_token_is_stored_hashed` (nessuna riga di `auth_tokens` contiene il token in chiaro).
- [ ] **Step 2: Esegui** → FAIL. **Step 3: Implementa.** **Step 4: Esegui** → PASS (anche i test dei task precedenti, adattati per usare un token).
- [ ] **Step 5: Appunti + commit** `feat: registrazione, login e token`.

### Task 10: Login e registrazione nell'app

**Files:**
- Create: `app/lib/features/auth/{auth_view_model,login_screen}.dart`, `app/test/auth_view_model_test.dart`, `docs/appunti/10-login-app.md`
- Modify: `main.dart` (porta d'ingresso: senza token → login), `api_client.dart` (header Bearer; 401 → logout locale)

**Interfaces:**
- Produces: `AuthViewModel extends ChangeNotifier` con `String? token`, `Future<void> restore()` (da `shared_preferences`, chiave `auth_token`), `login(email, password)`, `register(name, email, password)`, `logout()` (chiama `/auth/logout`, cancella il token anche se la chiamata fallisce).
- [ ] **Step 1: Test che falliscono**: `restore reads saved token`; `login saves token`; `wrong credentials set error message`; `logout clears token even if api fails`; `401 from any call logs out`.
- [ ] **Step 2: Esegui** → FAIL. **Step 3: Implementa** (una schermata con due modalità Accedi/Registrati). **Step 4: Esegui** → PASS.
- [ ] **Step 5: Appunti + commit** `feat: login e registrazione nell'app`.

### Task 11: Online! (Render + Supabase + GitHub Pages + dati demo)

**Files:**
- Create: `backend/Dockerfile` (`php:8.3-apache`, `docker-php-ext-install pdo_pgsql`, `composer install --no-dev`, `DocumentRoot /var/www/html/public`, `a2enmod rewrite`, Apache su `${PORT}`, all'avvio `php bin/migrate.php`), `backend/public/.htaccess` (tutto su `index.php`), `backend/.dockerignore`, `backend/bin/seed-demo.php`, `.github/workflows/web.yml`, `docs/appunti/11-deploy.md`
- Modify: `chat/…` e `api_client.dart`: se una richiesta supera 4 s, mostrare "Sto svegliando il server…"

**Interfaces:**
- Produces: `bin/seed-demo.php` → utente `demo@gymtracker.app` / password `demo1234`, 8 settimane di allenamenti mar/gio/sab (push / gambe / pull, carichi in crescita come nel mockup), idempotente (cancella e ricrea solo i dati dell'utente demo).
- [ ] **Step 1: Test che fallisce** `Integration/SeedDemoTest::test_seed_is_idempotent_and_creates_records`: lanciato due volte → stesso numero di allenamenti (≈ 24), almeno 3 record.
- [ ] **Step 2: Esegui** → FAIL. **Step 3: Implementa** seed. **Step 4: Esegui** → PASS.
- [ ] **Step 5: Docker in locale**: `docker build -t gymtracker-api backend` e `docker run -p 8080:8080 -e PORT=8080 --env-file backend/.env gymtracker-api` → `curl localhost:8080/health` → `{"ok":true}`.
- [ ] **Step 6: Supabase** (utente, guidato): nuovo progetto in UE, copia della stringa **Session pooler** → variabili `DB_*` con `DB_SSLMODE=require`.
- [ ] **Step 7: Render** (utente, guidato): Web Service da GitHub, runtime Docker, root `backend`, variabili `DB_*`, `GROQ_API_KEY`, `GROQ_MODEL`, `CORS_ORIGIN=https://<utente>.github.io`. Poi `php bin/seed-demo.php` dalla Shell di Render.
- [ ] **Step 8: GitHub Pages**: workflow con `subosito/flutter-action`, `flutter build web --release --base-href /GymTracker/ --dart-define=API_URL=<url Render>`, deploy con `actions/deploy-pages` (come TrackIt).
- [ ] **Step 9: Verifica dal telefono**: login demo, un esercizio registrato e salvato, ricarica → c'è ancora.
- [ ] **Step 10: Appunti + commit** `feat: deploy su Render, Supabase e GitHub Pages`.

### Task 12: Storico

**Files:**
- Create: `app/lib/features/history/{history_view_model,history_screen}.dart`, `app/test/history_view_model_test.dart`, `backend/tests/Integration/HistoryApiTest.php`, `docs/appunti/12-storico.md`
- Modify: `WorkoutController` (`GET /workouts`, `GET /workouts/{id}`)

**Interfaces:**
- Produces: `HistoryViewModel` con `List<WorkoutSummary> workouts`, `Map<String, List<WorkoutSummary>> byMonth()` (chiave `"OTTOBRE 2026"`, ordine dal più recente), `load()`.
- [ ] **Step 1: Test che falliscono**: backend `test_list_returns_only_closed_newest_first`, `test_list_includes_record_count`; Flutter `byMonth groups and orders`.
- [ ] **Step 2: Esegui** → FAIL. **Step 3: Implementa** schede come nel mockup (giorno settimana + numero, titolo, "1:08 · 4 esercizi · 16 serie", esercizi su una riga, badge "N PR") e foglio dal basso (`showModalBottomSheet`) con `SummaryView`. **Step 4: Esegui** → PASS.
- [ ] **Step 5: Appunti + commit** `feat: storico degli allenamenti`.

### Task 13: Progressi

**Files:**
- Create: `backend/src/Http/StatsController.php`, `backend/tests/Integration/ProgressApiTest.php`, `app/lib/models/progress.dart`, `app/lib/features/progress/{progress_view_model,progress_screen}.dart`, `docs/appunti/13-progressi.md`
- Modify: `AuthApiTest` (asserzione 404 su progress di altro utente), `pubspec.yaml` (`fl_chart`)

**Interfaces:**
- Produces: `GET /exercises/{id}/progress` → `{metric, best, first, points:[{date:"2026-10-17", value}]}` (un punto per allenamento chiuso o aperto; `value` = max kg, o max rip se `metric == "reps"`, stessa regola di `RecordService`).
- [ ] **Step 1: Test che falliscono**: `test_one_point_per_workout_with_max_weight`; `test_bodyweight_uses_reps_metric`; `test_empty_history_returns_no_points`.
- [ ] **Step 2: Esegui** → FAIL. **Step 3: Implementa** (menu a tendina esercizi, numero grande "+N kg/rip rispetto al primo", `LineChart` lime, ultime sessioni sotto). **Step 4: Esegui** → PASS.
- [ ] **Step 5: Appunti + commit** `feat: progressi per esercizio`.

### Task 14: Profilo

**Files:**
- Create: `backend/tests/Integration/ProfileApiTest.php`, `app/lib/models/profile.dart`, `app/lib/features/profile/{profile_view_model,profile_screen,training_calendar}.dart`, `app/test/training_calendar_test.dart`, `docs/appunti/14-profilo.md`
- Modify: `StatsController` (`GET /profile`)

**Interfaces:**
- Produces: `GET /profile` (vedi contratto; `recent_records` = ultimi 3 di `RecordService::compute`, più recenti prima); `List<DateTime?> calendarCells(DateTime today)` in `training_calendar.dart` → 91 celle, colonna = settimana lun–dom, l'ultima colonna è la settimana di oggi, giorni futuri `null`.
- [ ] **Step 1: Test che falliscono**: backend `test_training_days_last_91_days_distinct`, `test_recent_records_max_three_newest_first`; Flutter `calendarCells starts on monday 12 weeks before`, `future days are null`.
- [ ] **Step 2: Esegui** → FAIL. **Step 3: Implementa** (nome/email, calendario lime/grigio con bordo su oggi, mesi sopra, legenda + totale, ultimi record in arancione, *Esci*). **Step 4: Esegui** → PASS.
- [ ] **Step 5: Appunti + commit** `feat: profilo con calendario e record`.

### Task 15: Rifiniture — "L'ultima volta" e correzione via chat

**Files:**
- Create: `backend/tests/Integration/LastSetsApiTest.php`, `docs/appunti/15-rifiniture.md`
- Modify: `StatsController` (`GET /exercises/{id}/last`), `ChatViewModel` (dopo il riconoscimento dell'esercizio mostra "L'ultima volta: +42,5 × 8 · …"; se c'è una scheda non salvata, `send` passa `current_exercise` + `pending_sets` e una risposta `sets` **sostituisce** le serie di quella scheda)

- [ ] **Step 1: Test che falliscono**: backend `test_last_returns_previous_workout_sets_excluding_current`; Flutter `correction replaces pending card sets` (FakeApi risponde `sets` con la seconda serie a 7 rip) e `last sets hint shown after exercise recognized`.
- [ ] **Step 2: Esegui** → FAIL. **Step 3: Implementa.** **Step 4: Esegui** → PASS; prova reale: "la seconda erano 7".
- [ ] **Step 5: Appunti + commit** `feat: suggerimento ultima volta e correzioni via chat`.

### Task 16: README, ripasso e prova generale

**Files:**
- Create/Modify: `README.md` (cos'è, link all'app, credenziali demo, screenshot, stack, architettura a strati con lo schema, avvio in locale, test), `docs/appunti/16-ripasso.md` (le 3 domande di ogni task raccolte + le risposte brevi)

- [ ] **Step 1:** `composer test` e `flutter test` → tutto PASS; `flutter analyze` senza errori.
- [ ] **Step 2:** prova generale dal telefono sull'app online, cronometrata: login demo → allenamento con 3 esercizi (una frase ambigua, una correzione) → riepilogo → storico → progressi → profilo.
- [ ] **Step 3:** prova del piano B (tutto in locale sul portatile con Docker).
- [ ] **Step 4: Commit** `docs: README e ripasso finale`.

**Extra solo se avanza tempo:** build Android (`flutter build apk`), passaggio a Riverpod dei ViewModel.
