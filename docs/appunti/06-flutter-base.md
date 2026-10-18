# 06 · Flutter: progetto, tema, navigazione

## Cosa fa

- **Flutter installato** in `D:\flutter` (versione stable 3.47) e aggiunto al PATH. `flutter doctor` è verde per Chrome (web), che è quello che ci serve; l'avviso su Android riguarda la build per telefono, che è un extra.
- **Progetto Flutter** nella cartella `app/` (nome del pacchetto: `gymtracker`).
- **Tema scuro "Sala pesi"** (`lib/theme.dart`), con i colori del mockup.
- **Barra in basso** con le 4 voci (`lib/shell.dart`). Per ora la tab Allena mostra solo "API collegata", le altre sono segnaposto.
- **`ApiClient`** (`lib/api/api_client.dart`): l'unico punto dell'app che parla con il backend.
- **Numeri all'italiana** (`lib/util/format.dart`): `42,5`, `+45 kg × 8`, `12 rip`.
- Nel backend, il **CORS** (`CorsMiddleware`).

Come si avvia (due terminali):

```bash
# 1) backend
cd backend
php -S localhost:8080 -t public

# 2) app nel browser
cd app
flutter run -d chrome
```

Test: `cd app` poi `flutter test`. Controllo del codice: `flutter analyze`.

## Come è fatta un'app Flutter

In Flutter **tutto è un widget**: un pulsante, un testo, una schermata intera, l'app stessa. I widget si mettono uno dentro l'altro come scatole:

```
GymTrackerApp (MaterialApp: tema, titolo)
 └── Shell (Scaffold)
      ├── body: IndexedStack → una delle 4 schermate
      └── bottomNavigationBar: NavigationBar (Allena · Storico · Progressi · Profilo)
```

Due tipi di widget:
- **`StatelessWidget`**: non cambia da solo, si disegna in base a quello che riceve. Esempio: `_Placeholder`.
- **`StatefulWidget`**: ha uno **stato** che può cambiare. `Shell` ricorda quale tab è selezionata (`_tab`); quando tocchi una voce, `setState(() => _tab = i)` aggiorna il valore e Flutter **ridisegna** la schermata.

`IndexedStack` tiene vive tutte e 4 le schermate e ne mostra una sola: così, passando a Storico e tornando ad Allena, la chat non si perde.

## I file

- **`main.dart`**: il punto d'ingresso (come `index.php`). `runApp(...)` avvia l'app; `MaterialApp` imposta il tema e la prima schermata.
- **`theme.dart`**: `AppColors` contiene i colori; `buildTheme()` li applica a tutti i componenti, così nessuna schermata deve scegliere i colori a mano.
  - lime = azioni (pulsanti, voce attiva); arancione = solo record (lo useremo dal passo 8);
  - pulsanti alti almeno 48 px (`minimumSize`): si usano in palestra, con le mani sudate;
  - i caratteri (Barlow, Barlow Condensed) arrivano da Google Fonts con il pacchetto `google_fonts`.
- **`api_client.dart`**:
  - `get`, `post`, `put` restituiscono il JSON già decodificato;
  - se il server risponde con un errore, lancia `ApiException(status, messaggio)`. Il messaggio è quello italiano del backend ("Allenamento non trovato"), già pronto per l'utente;
  - server spento o niente rete → `ApiException(0, 'Server non raggiungibile…')`;
  - l'indirizzo del backend è `apiUrl`: in locale `http://localhost:8080`, online lo passeremo con `--dart-define=API_URL=...`;
  - `utf8.decode` serve perché senza le lettere accentate arriverebbero rovinate.
- **`format.dart`**: tre funzioni pure, cioè che restituiscono un valore senza modificare niente, quindi facili da testare.

## Il CORS, spiegato

Il browser, per sicurezza, **blocca** le risposte che arrivano da un indirizzo diverso da quello della pagina. L'app sarà su `https://gabfum11.github.io` e l'API su `https://...onrender.com`: indirizzi diversi, quindi senza CORS nessuna chiamata funzionerebbe.

Il `CorsMiddleware` aggiunge a ogni risposta l'header `Access-Control-Allow-Origin` ("questa origine può leggermi"). Per i `PUT` e le richieste con token, il browser fa prima una domanda di permesso con il metodo `OPTIONS` (si chiama **preflight**), e il middleware risponde subito `204`.

È aggiunto **per ultimo**, quindi è lo strato più esterno della cipolla: mette gli header anche sulle risposte di errore. Altrimenti, in caso di errore, il browser nasconderebbe il messaggio all'app.

In locale `CORS_ORIGIN=*` (tutti); online sarà solo l'indirizzo di GitHub Pages.

## Il test dei widget

`shell_test.dart` usa `testWidgets`: costruisce l'interfaccia in memoria, senza browser, e la usa come un utente.
- `tester.pumpWidget(...)` disegna il widget;
- `tester.tap(find.text('Progressi'))` tocca la voce;
- `tester.pumpAndSettle()` aspetta che finiscano animazioni e caricamenti;
- `expect(find.text('API collegata'), findsOneWidget)` controlla cosa si vede.

Il backend è finto (`MockClient` del pacchetto `http`), come `FakeLlmClient` in PHP.

## Come in TrackIt

| GymTracker (Flutter/Dart) | TrackIt (React Native/TS) |
|---|---|
| widget | componente |
| `StatefulWidget` + `setState` | `useState` |
| `MaterialApp` + `ThemeData` | `theme.ts` |
| `NavigationBar` | il tab navigator di Expo Router |
| `ApiClient` | le `fetch` verso `EXPO_PUBLIC_API_URL` |
| `--dart-define=API_URL` | variabili `EXPO_PUBLIC_*` |
| `flutter test` / `testWidgets` | Jest / React Native Testing Library |
| `CORSMiddleware` di FastAPI | il nostro `CorsMiddleware` |

## In Laravel sarebbe…

Lato backend il CORS in Laravel è già incluso: si configura in `config/cors.php` (`allowed_origins`), senza scrivere un middleware.

## Domande da colloquio

1. **Differenza tra `StatelessWidget` e `StatefulWidget`?** Il primo si disegna solo in base ai dati che riceve; il secondo ha uno stato interno che cambia nel tempo, e `setState` dice a Flutter di ridisegnarlo.
2. **Cos'è il CORS e perché serve?** Una regola del browser: una pagina può leggere risposte da un'altra origine solo se il server lo permette con gli header `Access-Control-Allow-*`. Non protegge il server, protegge l'utente.
3. **Perché tutte le chiamate passano da `ApiClient`?** Un solo punto per indirizzo, token, formato JSON e gestione degli errori. Se cambia qualcosa (per esempio il token al passo 10) si modifica un file solo.

## Prova per te

Aggiungi una quinta funzione di formattazione: `formatDuration(int minutes)` → `"1:08"` per 68 minuti, `"0:45"` per 45. La useremo nello Storico.
1. in `test/format_test.dart` aggiungi un `group('formatDuration', ...)` con `expect(formatDuration(68), '1:08')`, `expect(formatDuration(45), '0:45')` e `expect(formatDuration(0), '0:00')`;
2. `flutter test` → errore (la funzione non esiste);
3. scrivila in `lib/util/format.dart`. Suggerimento: `minutes ~/ 60` è la divisione intera, `minutes % 60` il resto, `.toString().padLeft(2, '0')` aggiunge lo zero davanti;
4. `flutter test` → verde.

Tienila: servirà davvero al passo 12.
