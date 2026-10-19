# 09 · Account, verifica email e token (come TrackIt)

## Cosa fa

| API | Cosa fa |
|---|---|
| `POST /auth/register` `{name, email, password}` | crea l'account (non ancora verificato) e manda un **codice a 6 cifre** via email → 201 |
| `POST /auth/verify-email` `{email, code}` | se il codice è giusto, verifica l'email e **apre la sessione** |
| `POST /auth/resend-code` `{email}` | manda un codice nuovo → 204 |
| `POST /auth/login` `{email, password}` | apre la sessione (403 se l'email non è verificata) |
| `POST /auth/refresh` `{refresh_token}` | scambia il refresh token con una coppia nuova |
| `POST /auth/logout` `{refresh_token}` | chiude la sessione di questo dispositivo → 204 |
| `POST /auth/logout-all` *(protetta)* | chiude le sessioni su tutti i dispositivi → 204 |
| `GET /me` *(protetta)* | `{id, name, email}` |

"Aprire la sessione" vuol dire rispondere con:

```json
{ "access_token": "eyJhbGciOi...", "refresh_token": "9f2c…(64 caratteri)", "user": {"id": 2, "name": "Gabriele", "email": "..."} }
```

Tutte le altre API (allenamenti, esercizi, parser) ora vogliono l'header `Authorization: Bearer <access_token>`, altrimenti **401 "Accesso richiesto"**. L'utente fisso di sviluppo non esiste più.

**In locale le email non partono**: il codice compare nel **terminale dove gira `php -S`**, in una riga come `EMAIL a ... il tuo codice è: 517825`. Online le manderà Brevo.

## I due token, spiegati

**Access token (JWT, 15 minuti).** È un testo in tre parti separate da punti: `intestazione.contenuto.firma`. Il contenuto si legge (è solo codificato in base64): `{"sub":"2","ver":0,"iat":...,"exp":...}`. La **firma** è calcolata con `JWT_SECRET`, che conosce solo il server: se qualcuno cambia `sub` per fingersi un altro utente, la firma non torna più e il token viene rifiutato. Il server non deve cercarlo nel database: gli basta verificare la firma e la scadenza (`exp`).

**Refresh token (casuale, 30 giorni).** 64 caratteri a caso, senza significato. Nel database c'è **solo il suo hash SHA-256**: chi rubasse il database non potrebbe usarli. Serve solo a ottenere un nuovo access token quando quello vecchio scade (al passo 10 l'app lo farà da sola, senza che l'utente se ne accorga).

**Perché due token?** L'access token va in ogni richiesta e dura poco: se viene intercettato, vale al massimo 15 minuti. Il refresh viaggia raramente e si può revocare (sta nel database).

## Le difese, una per una

| Difesa | Dove | Perché |
|---|---|---|
| Password con **Argon2id** | `password_hash(..., PASSWORD_ARGON2ID)` | algoritmo lento apposta: provare milioni di password diventa costosissimo |
| Stesso messaggio per email sconosciuta e password sbagliata | `AuthService::login` | non si rivela chi è registrato |
| **Rotazione** del refresh | `AuthService::refresh` | ogni refresh si usa una volta sola (`used_at`) |
| **Rilevamento furto** | refresh già usato che torna indietro | qualcuno l'ha copiato → si chiudono **tutte** le sessioni |
| `token_version` | colonna in `users`, campo `ver` nel JWT | incrementandolo, tutti gli access token già emessi smettono di valere (logout ovunque, furto) |
| Codice OTP con `random_int` | `AuthService::sendCode` | generatore crittografico, non prevedibile |
| 5 tentativi per codice, poi bruciato | `consumeCode` | 6 cifre = 1 milione di combinazioni: con 5 tentativi indovinare è quasi impossibile |
| `hash_equals` per confrontare il codice | `consumeCode` | confronto a tempo costante: non si deduce il codice dai tempi di risposta |
| Limiti (`RateLimiter`) | tabella `rate_limits` | 5 registrazioni/ora per IP, 10 login/15 min per IP, 3 codici/ora per account, 200 messaggi al bot/giorno |
| Token di reset non apre l'app | `userIdForAccessToken` rifiuta i JWT con `purpose` | serve al passo 9b |

## Il percorso di una richiesta protetta

```
GET /workouts/current  +  Authorization: Bearer eyJ...
   └─► AuthMiddleware: firma ok? scaduto? utente esiste? ver uguale a token_version?
          ├─ no  → UnauthorizedException → 401 "Accesso richiesto" (il controller non parte)
          └─ sì  → $request->withAttribute('user_id', 2) → WorkoutController
```

Il middleware è aggiunto **al gruppo** delle rotte protette in `AppFactory`: nessun controller può dimenticarsi il controllo.

## Il `RateLimiter`

Una riga per "secchio" (`login:10.0.0.1`, `parse:7`…) con un contatore e l'ora di azzeramento. Una sola query fa tutto:
- se la riga non c'è, la crea con contatore 1;
- se la finestra è scaduta, riparte da 1;
- altrimenti aggiunge 1.

È **atomica**: due richieste simultanee non possono leggere lo stesso valore. Se il contatore supera il limite → 429.

## Come in TrackIt

| GymTracker (PHP) | TrackIt (Python) |
|---|---|
| `TokenService` + `firebase/php-jwt` | `security.py` + `python-jose`/`jwt` |
| `AuthMiddleware` | `Depends(get_current_user)` |
| `password_hash(..., PASSWORD_ARGON2ID)` | `CryptContext(schemes=["argon2"])` |
| `RefreshTokenRepository::markUsed` + `used_at` | `rotate_refresh_token` + `used_at` |
| `users.token_version` / `ver` | stesso nome, stessa idea |
| `BrevoMailer` (curl) | `email_service.py` (httpx) |
| `RateLimiter` su database | `slowapi` (`@limiter.limit("5/hour")`) |

## In Laravel sarebbe…

- **Sanctum** (token opachi) o **Passport**/`tymon/jwt-auth` (JWT) al posto di `TokenService` e `AuthMiddleware`; le rotte protette con `->middleware('auth:sanctum')`.
- `Hash::make($password)`, con Argon2 configurabile in `config/hashing.php`.
- I limiti con il middleware `throttle:10,15` (10 richieste ogni 15 minuti).
- Le email con `Mail::to($email)->send(new CodiceMail($code))` e il driver di Brevo.

## Domande da colloquio

1. **Cosa c'è dentro un JWT e perché non si può falsificare?** Intestazione, contenuto e firma. Il contenuto è leggibile, ma la firma è calcolata con un segreto che ha solo il server: cambiando il contenuto la firma non corrisponde più.
2. **Perché access token corto e refresh token lungo?** L'access token viaggia in ogni richiesta: se viene rubato deve valere poco. Il refresh si usa raramente, è salvato (come hash) nel database e si può revocare.
3. **Come ti accorgi che un refresh token è stato rubato?** Con la rotazione ogni refresh vale una volta sola. Se ne arriva uno già usato, qualcuno ne ha una copia: chiudo tutte le sessioni dell'utente aumentando `token_version`.

## Prova per te

Il limite di login è 10 tentativi ogni 15 minuti. Abbassalo a **5**:
1. in `AuthApiTest::test_login_is_rate_limited_per_ip` cambia il ciclo da 10 a 5 tentativi;
2. `composer test` → fallisce (al sesto tentativo oggi risponde ancora 401, non 429);
3. in `AuthService::login` cambia il `10` in `5`;
4. `composer test` → verde.

Poi prova dal vero: registrati con `curl` (vedi sopra) e cerca il codice nel terminale del server.
