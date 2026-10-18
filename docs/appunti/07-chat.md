# 07 · La chat che registra le serie

## Cosa fa

La tab **Allena** è la chat vera:

1. **Inizia allenamento** → `POST /workouts`. Compaiono in basso, sempre visibili, **Prossimo esercizio**, **Fine allenamento** e il campo di testo.
2. Scrivi ("dip 45 per 8, poi 45x6") → `POST /parse` → compare la **scheda di conferma**.
3. Nella scheda:
   - tocchi un numero e lo cambi (su telefono si apre la tastiera numerica, con la virgola per i kg);
   - **×** toglie una serie, **+ Serie** ne aggiunge una copiando l'ultima;
   - se l'AI ha riconosciuto un esercizio esistente con un altro nome, compare **"Non è questo: crea «dip anelli»"**;
   - **Conferma** → se l'esercizio è nuovo `POST /exercises`, poi `PUT .../sets`. La scheda diventa "2 serie salvate · Modifica".
4. **Prossimo esercizio** → il bot chiede "Che esercizio stai facendo?".
5. Se **ricarichi la pagina** a metà, `GET /workouts/current` ritrova l'allenamento e le schede salvate.
6. Se rifai un esercizio già fatto oggi, la nuova scheda **parte dalle serie già salvate** e sostituisce la vecchia (il `PUT` sostituisce tutte le serie di quell'esercizio).

"Fine allenamento" per ora è disattivato: lo collega il passo 8.

## MVVM in pratica

```
ChatScreen (disegna)  ──legge e chiama──►  ChatViewModel (decide)  ──►  ApiClient (HTTP)
      ▲                                          │
      └──────── notifyListeners() ◄──────────────┘
```

- **`ChatViewModel`** (`features/chat/chat_view_model.dart`) contiene **tutta la logica**: la lista dei messaggi (`items`), l'allenamento aperto (`workoutId`), l'esercizio corrente, `busy` ("sto caricando"), `error`. Non sa niente di pulsanti o colori.
- **`ChatScreen`** e **`ConfirmCard`** disegnano lo stato e, quando tocchi qualcosa, chiamano un metodo del ViewModel (`vm.send(...)`, `vm.confirm(card)`).
- Quando lo stato cambia, il ViewModel chiama **`notifyListeners()`**; il `ListenableBuilder` nella schermata ridisegna tutto.
- `ChangeNotifier` è già incluso in Flutter: niente librerie in più.

Il vantaggio: **la logica si testa senza schermata**. `chat_view_model_test.dart` ha 15 test che usano il ViewModel come farebbe la schermata, contro un backend finto.

## Le scelte da saper spiegare

- **`sealed class ChatItem`**: un elemento della chat è *per forza* uno tra `BotMessage`, `UserMessage` e `CardItem`. Con `switch (item)` Dart ti obbliga a gestirli tutti: se un giorno ne aggiungi un quarto e ti dimentichi di disegnarlo, il codice non compila.
- **`SetDraft` tiene testo, non numeri**: mentre scrivi "42," il campo non è ancora un numero valido. I numeri si controllano solo alla **Conferma** (`parseItalianNumber`); se qualcosa non va: "Serie 2: controlla kg e ripetizioni".
- **`_run(...)`**: ogni chiamata al backend passa da qui, che accende `busy`, azzera l'errore e trasforma una `ApiException` nel messaggio da mostrare. Scritto una volta sola.
- **Un errore non perde niente**: se il server non risponde, la scheda resta com'è e il messaggio dell'utente resta nella chat.
- **`ListView(reverse: true)`**: la lista parte dal fondo, così il messaggio più recente è sempre visibile sopra il campo di testo.
- **`KeyedSubtree` con `revision`**: quando togli la serie 1, la vecchia serie 2 diventa la 1. Cambiando la chiave, Flutter ricrea i campi con i valori giusti invece di riusare quelli vecchi.
- **Il backend finto** (`test/fake_backend.dart`): per ogni "METODO /percorso" decidi la risposta, e lui registra tutte le chiamate. Così i test controllano anche l'**ordine** delle chiamate (prima `POST /exercises`, poi `PUT`).

## Un bug trovato dalla prova vera

Provando con Groq vero, "panca 70" arrivava con `"weighted": true`: l'app avrebbe mostrato "+70 kg" come se fosse una zavorra. Il prompt non diceva cosa significasse "weighted". Aggiunta la regola ("true SOLO per esercizi a corpo libero con zavorra…") con un test, e verificato: panca e squat `false`, trazioni `true`.

È il motivo per cui, oltre ai test con l'AI finta, si prova sempre anche quella vera.

## Novità di Dart

- **`sealed class`** + **pattern matching**: `case ParsedSets(:final exercise, :final sets):` controlla il tipo e tira fuori i campi in un colpo.
- **`late final _chat = ChatViewModel(...)..load();`**: `late` lo crea alla prima lettura; `..` (cascade) chiama `load()` sull'oggetto appena creato e restituisce l'oggetto stesso.
- **`??=`**: `card.exerciseId ??= ...` esegue la creazione solo se l'id manca.
- **`for (final (i, draft) in card.sets.indexed)`**: scorre la lista con indice e valore insieme.
- **`[...?previous?.sets, ...]`**: unisce le serie vecchie (se ci sono) con le nuove.

## Come in TrackIt

| GymTracker | TrackIt |
|---|---|
| `ChatViewModel extends ChangeNotifier` | uno *hook* o uno stato con `useState`/`useReducer` |
| `notifyListeners()` + `ListenableBuilder` | `setState` che fa ridisegnare il componente |
| `ConfirmCard` | la schermata di conferma della spesa dell'assistente |
| `FakeBackend` | i mock di `fetch` nei test |

## In Laravel sarebbe…

Lato backend non cambia niente in questo passo. Per curiosità, il "riconosci l'esercizio o crealo" (`POST /exercises`) in Eloquent sarebbe `Exercise::firstOrCreate(['user_id' => $id, 'name' => $name])`.

## Domande da colloquio

1. **Cos'è MVVM e perché l'hai usato?** Separa la schermata (View) dalla logica e dallo stato (ViewModel). La logica si testa senza interfaccia, e la schermata resta semplice: legge lo stato e chiama metodi.
2. **Cosa succede se il server non risponde mentre confermo?** `ApiClient` lancia `ApiException`, `_run` la cattura e mostra il messaggio; la scheda resta non salvata con i numeri intatti, e si può riprovare.
3. **Perché i campi della scheda tengono testo e non numeri?** Perché durante la digitazione il valore può essere incompleto ("42,"). Si converte e si controlla solo alla conferma, con un messaggio che dice quale serie è sbagliata.

## Prova per te

Il messaggio di benvenuto è lungo. Cambialo e fai in modo che un test lo controlli:
1. in `chat_view_model_test.dart`, nel test "senza allenamento aperto", aggiungi `expect((vm.items.single as BotMessage).text, startsWith('Ciao!'));` e lancia `flutter test` → verde (è già così);
2. cambia l'`expect` con il testo nuovo che vuoi, per esempio `startsWith('Pronto?')` → `flutter test` → **rosso**;
3. cambia il testo in `ChatViewModel.load()` → `flutter test` → verde.

Poi provalo nel browser (due terminali: `php -S localhost:8080 -t public` in `backend`, `flutter run -d chrome` in `app`).
