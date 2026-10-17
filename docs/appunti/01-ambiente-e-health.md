# 01 · Ambiente e prima API (`GET /health`)

## Cosa fa

- **Git**: il progetto è un repository. `main` è la versione "buona"; lavoriamo sul branch `sviluppo` e lo uniamo a `main` alle tappe importanti (la prima: quando l'app va online).
- **Docker** (`docker-compose.yml`): avvia PostgreSQL 16 sul tuo PC con due database: `gymtracker` (sviluppo) e `gymtracker_test` (lo usano i test, che lo svuotano ogni volta).
  - `docker compose up -d` → avvia · `docker compose down` → ferma (i dati restano nel volume `dbdata`).
- **Composer** (`backend/composer.json`): le librerie PHP. Sono in `backend/vendor/`, che non va su Git (si ricrea con `composer install`).
- **La prima API**: `GET /health` risponde `{"ok": true}`.

Come si avvia:

```bash
cd backend
php -S localhost:8080 -t public     # server di sviluppo integrato in PHP
curl http://localhost:8080/health   # → {"ok":true}
composer test                       # esegue i test
```

## Il percorso di una richiesta

```
browser/curl ──► public/index.php ──► AppFactory (rotte) ──► HealthController ──► JsonResponse
```

1. `public/index.php` è l'unico file che il server esegue. Carica le librerie (`vendor/autoload.php`), legge `.env` e crea l'app.
2. `AppFactory::create()` registra le rotte: "se arriva `GET /health`, chiama `HealthController`".
3. `HealthController` è una classe con un solo metodo `__invoke`: riceve la richiesta e una risposta vuota, e restituisce la risposta riempita.
4. `JsonResponse::with()` trasforma un array PHP in JSON e imposta l'header `Content-Type: application/json`.

## Perché così

- **Slim invece di Laravel**: Slim fa solo il routing. Tutto il resto lo scriviamo noi, quindi lo capisci e lo sai spiegare. Laravel fa molte cose "per magia".
- **`AppFactory` separato da `index.php`**: così i test possono creare l'app e mandarle richieste finte, senza accendere un server.
- **Errori sempre in JSON** (`forceContentType`): il frontend Flutter si aspetta JSON. Con `APP_DEBUG=true` vedi i dettagli dell'errore; online sarà `false` e mostrerà solo un messaggio generico.
- **`declare(strict_types=1)`** in cima a ogni file: PHP non converte i tipi di nascosto (passare `"5"` a un parametro `int` diventa un errore). Meno sorprese.

## Come in TrackIt

| GymTracker (PHP) | TrackIt (Python) |
|---|---|
| `composer.json` + `composer install` | `requirements.txt` + `pip install -r` |
| `vendor/` | il `venv` |
| `public/index.php` + `php -S` | `app/main.py` + `uvicorn app.main:app` |
| `$app->get('/health', HealthController::class)` | `@app.get("/health")` |
| `JsonResponse::with($response, [...])` | FastAPI converte da solo il `dict` in JSON |
| `tests/` + PHPUnit | `tests/` + pytest |
| `.env` + phpdotenv | `.env` + python-dotenv |

## Il test

`tests/Unit/HealthTest.php` crea l'app, le manda una richiesta `GET /health` finta e controlla tre cose: codice 200, header JSON, corpo `{"ok": true}`.
L'abbiamo visto **fallire prima** ("Class App\AppFactory not found") e **passare dopo**: è la prova che il test controlla davvero qualcosa.

## Domande da colloquio

1. **Cos'è un middleware?** Un pezzo di codice che sta "attorno" a ogni richiesta, prima o dopo il controller. Qui ne usiamo due: il routing (sceglie il controller) e la gestione degli errori (trasforma le eccezioni in risposte JSON).
2. **Perché `vendor/` e `.env` non sono su Git?** `vendor/` si ricrea con `composer install`, ed è codice di altri. `.env` contiene segreti, come la chiave di Groq e la password del database.
3. **Cos'è PSR-7?** Lo standard PHP per rappresentare richieste e risposte HTTP come oggetti. Slim lo usa, e per questo il controller riceve `ServerRequestInterface` e `ResponseInterface`.

## Prova per te

Aggiungi una rotta `GET /version` che risponda `{"version": "0.1.0"}`:
1. scrivi prima il test in `tests/Unit/VersionTest.php` (copia `HealthTest` e cambia percorso e valore atteso);
2. lancia `composer test` e guardalo **fallire**;
3. crea `src/Http/VersionController.php` e registra la rotta in `AppFactory`;
4. lancia di nuovo `composer test` e guardalo **passare**.

Poi cancella tutto con `git checkout .` e `git clean -fd src tests`, oppure tienilo: decidi tu.
