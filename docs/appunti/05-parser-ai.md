# 05 · Il parser AI (Groq)

## Cosa fa

Nuova API: `POST /parse` con `{"text": "dip 45 per 8, poi 45x6"}` → il bot risponde con **uno di tre esiti**:

```json
{ "type": "sets", "exercise": "Dip zavorrati", "typed_name": "dip", "matched_existing_id": 12, "weighted": true,
  "sets": [ {"weight_kg": 45.0, "reps": 8}, {"weight_kg": 45.0, "reps": 6} ] }

{ "type": "exercise", "exercise": "Panca piana", "typed_name": "panca", "matched_existing_id": null, "weighted": false }

{ "type": "question", "text": "8 sono le ripetizioni o i kg?" }
```

**Non salva niente.** Il risultato diventa la scheda di conferma nell'app; si salva solo quando l'utente preme Conferma (con il `PUT` del passo 4).

Campi opzionali della richiesta:
- `current_exercise`: l'esercizio in corso, per capire "45 per 8" senza nome;
- `pending_sets`: le serie della scheda non ancora confermata, per capire correzioni come "la seconda erano 7".

## I pezzi

```
ParseController ─► ParserService ─► LlmClient (interfaccia)
                        │               ├── GroqClient      (vero: chiama Groq con curl)
                        │               └── FakeLlmClient   (finto: nei test)
                        └─► SetValidator (controlla OGNI campo della risposta dell'AI)
```

- **`LlmClient`** è un'**interfaccia**: un contratto che dice "chi mi implementa sa fare `completeJson($system, $user)` e restituisce una stringa JSON". Non dice *come*.
- **`GroqClient`** la implementa davvero: una richiesta HTTP `POST` a Groq con `curl`.
- **`FakeLlmClient`** (in `tests/Support`) la implementa per finta: risponde quello che gli dici. Così i test non usano internet, non consumano la quota di Groq e sono sempre uguali.
- **`ParserService`** costruisce il prompt, chiama l'AI e **controlla la risposta**.

## La regola d'oro: l'AI non è fidata

Un modello linguistico può sbagliare, inventare o rispondere in un formato diverso. Per questo `ParserService::check()` tratta la risposta come l'input di uno sconosciuto:

| Se l'AI… | Allora |
|---|---|
| risponde con JSON rotto | il bot chiede "Non ho capito, puoi riscriverlo? Es. 45 kg × 8…" |
| inventa un `type` che non esiste | idem |
| dà 1000 kg o 0 ripetizioni | idem (passa da `SetValidator`, che lancia `ValidationException`) |
| indica un esercizio esistente con un id non nell'elenco (999) | l'id viene ignorato |
| riconosce un esercizio esistente | si usa il **nome salvato** ("Dip zavorrati"), non quello scritto ("dips") |
| non riesce a rispondere (rete, timeout, limite di Groq) | `LlmException` → **503** con "Il bot non risponde, riprova tra poco" |

Il messaggio del 503 è **fisso**: i dettagli tecnici (che potrebbero contenere la risposta di Groq) vanno solo nel log del server, con `error_log`.

## Il prompt

Sta in `ParserService::systemPrompt()`. Contiene:
1. i tre formati di risposta ammessi;
2. le regole: corpo libero = 0 kg, "+10" = zavorra, "3 serie da 8 con 45" = tre serie uguali, **se non è chiaro fai una domanda**, le correzioni restituiscono la lista completa;
3. l'elenco degli esercizi dell'utente (`12: Dip zavorrati`), così l'AI può riconoscere "dips" o "parallele" come lo stesso esercizio;
4. l'esercizio corrente e le serie in sospeso.

Le impostazioni della chiamata (in `GroqClient`):
- `response_format: json_object` → il modello deve rispondere con un JSON;
- `temperature: 0` → risposte stabili, non "creative";
- timeout di **15 secondi**.

La chiave sta solo sul server (`.env` → `GROQ_API_KEY`), mai nell'app.

## La prova con Groq vero (18 ottobre)

| Frase | Risultato |
|---|---|
| "dip 45kg 8" | Dip zavorrati (riconosciuto), 45 × 8 |
| "45x8, 45x6, 40x8" (esercizio corrente: Dip) | 3 serie |
| "la prima 45 per 8, poi 45 per 6, l'ultima 40 per 8" | 3 serie |
| "3 serie da 8 con 80 di panca" | Panca piana (riconosciuta), 3 × 80 kg × 8 |
| "trazioni senza peso 10, 9, 8" | Trazioni (nuovo), 0 kg × 10/9/8 |
| "parallele +20 per 6" | Dip zavorrati (sinonimo riconosciuto), zavorra 20 kg |
| "squat 8 10" | **domanda**: "8 sono le ripetizioni o i kg?" |
| "la seconda erano 7" (scheda 45×8, 45×6) | serie corrette: 45×8, **45×7** |

Circa **1 secondo** per frase. La prova ha trovato un difetto (`typed_name` conteneva tutta la frase): corretto con una regola nel prompt e un test.

**Due cose imparate:**
- **Certificati su Windows.** Il primo tentativo ha dato `SSL certificate problem`. PHP su Windows non sa dove sono i certificati per verificare le connessioni HTTPS. La soluzione giusta **non** è disattivare il controllo (sarebbe una falla di sicurezza), ma indicarli nel `php.ini`: `curl.cainfo` e `openssl.cafile` puntano a `D:/Git/mingw64/etc/ssl/certs/ca-bundle.crt`. Su Render (Linux) non serve.
- **Limite del piano gratuito di Groq**: 8000 token al minuto. Ogni messaggio ne usa circa 1000, quindi circa 8 messaggi al minuto. Superato il limite Groq risponde `429`, che diventa il nostro 503 "Il bot non risponde, riprova tra poco". Per una persona che si allena basta e avanza.

## Novità di PHP

- **`interface`** e **`implements`**: vedi sopra.
- **Heredoc** `<<<PROMPT ... PROMPT;`: una stringa su più righe, con le variabili `{$list}` dentro.
- **`curl_*`**: le funzioni per fare richieste HTTP, che vengono dall'estensione `curl` attivata nel `php.ini`.
- **`catch (ValidationException)`** senza variabile: "se succede questo, non mi serve sapere i dettagli".
- **`array_column($existing, null, 'id')`**: trasforma la lista in un array indicizzato per id (`[12 => [...]]`), per trovare un esercizio dal suo id in un colpo.

## Come in TrackIt

È lo stesso schema del tuo assistente ("ieri 12€ pizza" → spesa):

| GymTracker | TrackIt |
|---|---|
| `ParserService` | `business_logic/categorization.py` / `domande.py` |
| `GroqClient` (curl a mano) | client della libreria `groq` |
| `response_format: json_object` | stesso parametro |
| `openai/gpt-oss-20b` | stesso modello |
| `FakeLlmClient` nei test | i mock in `test_assistente.py` |

## In Laravel sarebbe…

- La chiamata con il client HTTP di Laravel: `Http::withToken($key)->timeout(15)->post($url, [...])->json('choices.0.message.content')`.
- L'interfaccia si collega all'implementazione nel **service container**: `$this->app->bind(LlmClient::class, GroqClient::class)` in un service provider.
- Nei test si usa `Http::fake([...])` per simulare la risposta di Groq.

## Domande da colloquio

1. **Perché un'interfaccia per l'AI?** Per poter cambiare fornitore (Groq, OpenAI, Claude) scrivendo una sola classe, e per testare il parser con un'AI finta, senza rete né costi.
2. **Ti fidi della risposta dell'AI?** No: la valido come qualsiasi input esterno. Se non torna, il bot fa una domanda invece di salvare un dato sbagliato; e comunque nulla si salva senza la conferma dell'utente.
3. **Dove sta la chiave API e perché?** Solo sul server, in una variabile d'ambiente. Se fosse nell'app chiunque potrebbe estrarla e usare il mio credito.

## Prova per te

Il limite di 500 caratteri per un messaggio è generoso. Portalo a **200**:
1. in `ParseApiTest` aggiungi `test_too_long_text_returns_400` con `['text' => str_repeat('a', 201)]` → atteso **400**;
2. `composer test` → fallisce (201 caratteri oggi vanno bene);
3. cambia `MAX_TEXT` in `ParseController`;
4. `composer test` → verde.
