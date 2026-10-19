# 10 · L'accesso nell'app

## Cosa fa

Le schermate del mockup (https://claude.ai/artifact/7WpwVpv3X3agSgxPoYiVyT):

```
Benvenuto (solo la prima volta) ─► Accesso ─► app
                                     ├─► Registrazione ─► Codice ─► app
                                     └─► Password dimenticata ─► Codice ─► Nuova password ─► Accesso
```

- **Benvenuto**: nome, "Crea i tuoi allenamenti personalizzati", esempio di chat, **Continua**. Dopo la prima volta non si vede più (`welcome_seen` salvato sul dispositivo).
- **Accesso**: se l'email non è ancora verificata, l'app manda da sola un codice nuovo e passa alla schermata del codice.
- **Codice**: un campo con le cifre grandi; sul telefono la tastiera propone il codice arrivato via email (`AutofillHints.oneTimeCode`).
- **Profilo** (provvisorio): nome, email, **Esci**, **Esci da tutti i dispositivi**.
- Riapri l'app il giorno dopo: entri **senza password**, grazie al refresh token salvato.

Per provarlo in locale: backend acceso (`php -S localhost:8080 -t public`), poi `flutter run -d chrome`. Registrati: **il codice lo trovi nel terminale del backend**.

## Dove stanno i token

| Token | Dove | Perché |
|---|---|---|
| access token (15 min) | solo in memoria (`api.token`) | se chiudi l'app sparisce: va bene, si rifà con il refresh |
| refresh token (30 giorni) | `shared_preferences` (sul web: lo storage del browser) | serve a rientrare senza password |

## Il rinnovo automatico (la parte più interessante)

Quando l'access token scade, qualsiasi chiamata riceve **401**. Allora:

```
ApiClient riceve 401 su /workouts/current
  └─► chiama onUnauthorized()  (lo fornisce AuthViewModel)
        └─► POST /auth/refresh con il refresh salvato → coppia nuova (il vecchio refresh è bruciato)
  └─► ripete /workouts/current UNA volta con il token nuovo
Se anche il rinnovo fallisce → si cancella tutto e si torna all'accesso.
```

Due dettagli che contano:
- **Le rotte `/auth/` non si rinnovano** (altrimenti un 401 del login proverebbe a rinnovare, all'infinito).
- **Un solo rinnovo alla volta.** Se due richieste scadono insieme, entrambe chiederebbero un rinnovo con lo **stesso** refresh. Il secondo arriverebbe al server già bruciato, e il server penserebbe a un furto e **chiuderebbe tutte le sessioni** (passo 9). Per questo `_refreshAccess` conserva la `Future` del rinnovo in corso (`_refreshing ??= ...`): chi arriva dopo aspetta la stessa. C'è un test apposta.

## Bug trovati dai test in questo passo

1. **Risposte che non sono JSON.** Se il server risponde con una pagina HTML (succede su Render mentre si sveglia), `jsonDecode` lanciava un errore non gestito e l'app si bloccava. Ora diventa `ApiException` con "Il server non risponde, riprova tra poco".
2. **Righe troppo larghe sugli schermi stretti** ("Non hai un account? Registrati", il nome in grande): ora vanno a capo (`Wrap`, `Flexible`) invece di uscire dallo schermo.

## Novità di Flutter

- **`enum AuthStep`** + `switch` in `AuthFlow`: una schermata per valore. Se aggiungi un passo e non lo disegni, non compila.
- **`TextEditingController`**: contiene il testo di un campo; va chiuso in `dispose()`.
- **`AutofillGroup` + `autofillHints`**: dicono al browser/telefono cosa c'è nel campo (email, password, codice), così propone i valori salvati.
- **`Semantics(liveRegion: true)`**: i messaggi d'errore vengono letti dai lettori di schermo appena compaiono.
- **`ValueKey(auth.user?.id)` su `Shell`**: cambiando utente, Flutter ricrea la Shell (e la chat) da zero.
- **`shared_preferences`**: piccolo archivio chiave → valore sul dispositivo (sul web usa il `localStorage` del browser).

## Come in TrackIt

| GymTracker (Flutter) | TrackIt (React Native) |
|---|---|
| `AuthViewModel` | il contesto/hook di autenticazione |
| `shared_preferences` | `AsyncStorage` / `SecureStore` |
| `onUnauthorized` in `ApiClient` | l'intercettore che rinnova il token su 401 |
| `_refreshing ??=` | la promessa di refresh condivisa |

## In Laravel sarebbe…

Lato app non cambia niente. Lato server, con **Sanctum** in modalità SPA si userebbero i cookie di sessione invece dei token, e niente refresh da gestire nell'app.

## Domande da colloquio

1. **Cosa succede quando l'access token scade mentre uso l'app?** `ApiClient` riceve 401, chiede un rinnovo con il refresh token, ripete la richiesta una volta. L'utente non se ne accorge; se il rinnovo fallisce torna all'accesso.
2. **Perché un solo rinnovo alla volta?** Con la rotazione, ogni refresh vale una volta: due rinnovi paralleli farebbero sembrare il secondo un furto e il server chiuderebbe tutte le sessioni.
3. **Dove salvi i token e perché lì?** L'access solo in memoria (dura poco); il refresh nello storage del dispositivo, perché serve a rientrare. Sul web lo storage è leggibile da JavaScript della stessa pagina: per questo il refresh è ruotato e revocabile.

## Prova per te

Il benvenuto oggi compare solo la prima volta. Aggiungi nel Profilo un pulsante **"Rivedi il benvenuto"** che azzera `welcome_seen`:
1. in `auth_view_model_test.dart` scrivi un test: dopo `continueFromWelcome()` chiami `resetWelcome()` e controlli che `getBool('welcome_seen')` sia `false`;
2. `flutter test` → errore (il metodo non esiste);
3. scrivi `resetWelcome()` in `AuthViewModel` (`prefs.setBool('welcome_seen', false)`);
4. aggiungi il `TextButton` in `profile_screen.dart`.
