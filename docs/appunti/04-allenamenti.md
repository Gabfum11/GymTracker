# 04 · Esercizi e allenamenti: le prime API vere

## Cosa fa

Sei nuove API (per ora con un **utente fisso**, l'utente di sviluppo con id 1: il login arriva al passo 9).

| API | Cosa fa |
|---|---|
| `GET /exercises` | elenco degli esercizi dell'utente, in ordine alfabetico |
| `POST /exercises` `{name, is_weighted_bodyweight}` | crea l'esercizio; se esiste già (anche scritto "dip" invece di "Dip") restituisce quello: **201** creato, **200** esisteva |
| `POST /workouts` | avvia un allenamento; se ce n'è già uno aperto restituisce quello |
| `GET /workouts/current` | l'allenamento in corso, o `null` (serve a ritrovarlo se ricarichi la pagina) |
| `PUT /workouts/{id}/exercises/{exerciseId}/sets` `{sets: [...]}` | **sostituisce** le serie di quell'esercizio (lista vuota = toglie l'esercizio) |
| `POST /workouts/{id}/finish` | chiude l'allenamento |

Tutte (tranne `GET /exercises`) rispondono con il **riepilogo dell'allenamento** (`WorkoutSummary`):

```json
{ "id": 1, "title": "Dip zavorrati", "started_at": "...", "ended_at": null, "duration_minutes": 0,
  "set_count": 2, "exercise_count": 1, "records": [],
  "exercises": [ { "id": 1, "name": "Dip zavorrati", "is_weighted_bodyweight": true,
                   "sets": [ {"weight_kg": 45.0, "reps": 8, "is_record": false}, ... ] } ] }
```

Provalo a mano:

```bash
php bin/migrate.php --dev-user          # una volta sola: crea l'utente di sviluppo
php -S localhost:8080 -t public
curl -X POST http://localhost:8080/workouts
curl -X POST http://localhost:8080/exercises -H "Content-Type: application/json" -d "{\"name\":\"dip\"}"
curl -X PUT http://localhost:8080/workouts/1/exercises/1/sets -H "Content-Type: application/json" -d "{\"sets\":[{\"weight_kg\":\"42,5\",\"reps\":8}]}"
```

## I tre strati, finalmente al completo

```
HTTP ─► WorkoutController ─► WorkoutService ─► WorkoutRepository / SetRepository / ExerciseRepository ─► PostgreSQL
        (legge la richiesta,   (le regole)        (solo SQL)
         risponde JSON)
```

Segui `PUT .../sets` dall'inizio alla fine:

1. **`WorkoutController::saveSets`** legge l'id dall'indirizzo (`$args['id']`), il corpo JSON (`getParsedBody()`) e l'utente (`getAttribute('user_id')`). Chiama il service. Nessuna regola qui.
2. **`WorkoutService::saveSets`** applica le regole:
   - l'allenamento esiste **ed è di questo utente**? Altrimenti `NotFoundException` → 404;
   - è ancora aperto? Altrimenti `ConflictException` → 409;
   - l'esercizio è dell'utente? Altrimenti 404;
   - le serie sono valide? `SetValidator::sets` (passo 3), altrimenti 400.
3. **`SetRepository::replaceForExercise`** cancella le vecchie serie e scrive le nuove, **in una transazione**: se qualcosa va storto a metà, le vecchie restano.
4. **`SummaryService::build`** trasforma le righe del database nel riepilogo (titolo, durata, conteggi, esercizi raggruppati).
5. Il controller risponde con `JsonResponse::with(...)`.

## Le scelte da saper spiegare

- **`PUT` che sostituisce, non `POST` che aggiunge.** La scheda di conferma nell'app manda sempre **tutte** le serie dell'esercizio. Se la rimandi due volte (doppio tocco, rete lenta) il risultato è lo stesso: si dice che `PUT` è **idempotente**. Con un `POST` che aggiunge, un doppio tocco duplicherebbe le serie.
- **Anche "Avvia allenamento" è idempotente**: se esiste già un allenamento aperto, `POST /workouts` restituisce quello. In più il database ha la regola "un solo allenamento aperto" (passo 2).
- **404 anche per le cose degli altri.** Se l'utente 2 chiede l'allenamento dell'utente 1, risponde "non trovato", non "vietato": così non rivela nemmeno che esiste. Per questo ogni query ha `WHERE user_id = ?`.
- **Le eccezioni diventano codici HTTP in un solo posto** (`AppFactory`, `setErrorHandler`): i controller restano puliti, senza `try/catch` ovunque.
- **Iniezione delle dipendenze "a mano".** I repository ricevono il `PDO` nel costruttore, il service riceve i repository, il controller riceve il service. Li colleghiamo in `AppFactory::addApiRoutes`. Nei test possiamo passare un database diverso: è il motivo di tutto questo.
- **L'ordine degli esercizi non cambia se modifichi.** Le serie riscritte mantengono l'ora della prima serie (`COALESCE(min(created_at), now())`), quindi Dip resta primo anche se lo correggi dopo aver fatto Panca.
- **`JSON_PRESERVE_ZERO_FRACTION`**: senza, PHP scrive `45.0` come `45` e Flutter riceverebbe a volte un intero e a volte un decimale. Il test l'ha scoperto.

## Novità di PHP in questo passo

- **Costruttore con promozione delle proprietà**: `public function __construct(private PDO $pdo) {}` dichiara la proprietà `$this->pdo` e la assegna in una riga sola.
- **`??=`**: `$x ??= valore` assegna solo se `$x` non esiste ancora.
- **`?->`**: `$ended?->format(...)` chiama il metodo solo se `$ended` non è `null`, altrimenti restituisce `null`.
- **`throw` come espressione**: `return $x ?? throw new NotFoundException(...)`.
- **Rotte con callable**: `[$workouts, 'start']` vuol dire "chiama il metodo `start` dell'oggetto `$workouts`".
- **`{id:\d+}`** nella rotta: il pezzo variabile accetta solo cifre.

## Come in TrackIt

| GymTracker | TrackIt |
|---|---|
| `WorkoutController` | un file in `app/routers/` |
| `WorkoutService` | una funzione in `app/business_logic/` |
| `WorkoutRepository` + SQL | query SQLAlchemy (`db.query(Expense).filter(...)`) |
| `addApiRoutes` che crea gli oggetti | `Depends(get_db)` di FastAPI che passa la sessione |
| `setErrorHandler(NotFoundException → 404)` | `raise HTTPException(status_code=404)` |
| middleware che mette `user_id` | `Depends(get_current_user)` |

## In Laravel sarebbe…

- Rotte in `routes/api.php`: `Route::put('/workouts/{workout}/exercises/{exercise}/sets', [WorkoutController::class, 'saveSets']);`
- **Route model binding**: Laravel carica da solo `Workout $workout` dall'id nell'indirizzo.
- I repository di solito non ci sono: si usano i modelli **Eloquent** (`$workout->sets()->delete()`), e le transazioni con `DB::transaction(fn () => ...)`.
- La "proprietà" (l'allenamento è tuo?) si controlla con una **Policy** (`$this->authorize('update', $workout)`).
- La dependency injection la fa il **service container**: scrivi `__construct(WorkoutService $service)` e Laravel crea tutto da solo.

## Domande da colloquio

1. **Differenza tra `PUT` e `POST`?** `PUT` sostituisce una risorsa ed è idempotente (ripeterlo dà lo stesso risultato); `POST` crea o esegue un'azione e ripeterlo può creare duplicati.
2. **Perché un service separato dal controller?** Il controller parla HTTP; il service contiene le regole. Così le regole si possono riusare (per esempio da uno script) e testare senza HTTP, e il controller resta corto.
3. **Perché 404 e non 403 per l'allenamento di un altro utente?** Un 403 confermerebbe che quell'id esiste. Con 404 non si rivela niente.

## Prova per te

Aggiungi la regola "non si può chiudere un allenamento vuoto":
1. in `WorkoutApiTest` scrivi `test_finish_empty_workout_returns_409`: avvia un allenamento e chiudilo subito → ti aspetti **409**;
2. `composer test` → fallisce (oggi risponde 200);
3. in `WorkoutService::finish`, prima di chiudere, se `$this->sets->forWorkout($workoutId)` è vuoto lancia `ConflictException('Aggiungi almeno un esercizio prima di chiudere')`;
4. `composer test` → attenzione: ora fallisce anche `test_finish_closes_workout`, che chiude un allenamento vuoto. Sistemalo aggiungendo un esercizio prima del `finish`. È normale: cambiare una regola può cambiare i test esistenti.

Poi decidi se tenerla: è una scelta di prodotto (secondo me ha senso).
