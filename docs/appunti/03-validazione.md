# 03 · Validazione delle serie

## Cosa fa

`src/Service/SetValidator.php` controlla i dati **prima** che arrivino al database. Ha due metodi pubblici:

| Metodo | Riceve | Restituisce | Se il dato è sbagliato |
|---|---|---|---|
| `SetValidator::sets($raw)` | una lista di serie, es. `[['weight_kg' => '42,5', 'reps' => 8]]` | la lista "pulita": `[['weight_kg' => 42.5, 'reps' => 8]]` | lancia `ValidationException` con un messaggio in italiano |
| `SetValidator::exerciseName($raw)` | un nome, es. `"  dip   zavorrati "` | `"Dip zavorrati"` | idem |

Le regole (spec §5.2):
- kg tra **0 e 500**, a passi di **0,25** (0 = corpo libero);
- ripetizioni **intere** tra **1 e 100**;
- da 1 a **20 serie**;
- nome esercizio da 1 a **60 caratteri**.

## Perché così

- **Si valida al confine.** Tutto ciò che entra (dal telefono o dall'AI) passa da qui. Dentro l'app possiamo poi fidarci dei dati: `weight_kg` è sempre un `float` valido e `reps` sempre un `int`.
- **Normalizzare, non solo controllare.** `"42,5"` (testo, con la virgola come la scrive un italiano) diventa `42.5` (numero). Così il resto del codice non deve preoccuparsi dei formati.
- **Un'eccezione invece di `true/false`.** Se il dato è sbagliato non si va avanti: l'eccezione interrompe tutto e porta con sé il messaggio da mostrare. Il controller (passo 4) la trasformerà in una risposta `400` con `{"error": "Serie 1: le ripetizioni…"}`.
- **`ValidationException` è nostra** (estende `RuntimeException`): così il controller può distinguere "dato sbagliato dell'utente" (400) da "errore del server" (500).
- **`static`**: il validatore non ha uno stato da ricordare, è solo una funzione di controllo. Si chiama direttamente `SetValidator::sets(...)`.

## I punti del codice da guardare

- `toNumber()` accetta `45`, `42.5`, `"42.5"` e `"42,5"`. La virgola diventa punto con `str_replace(',', '.', ...)`, poi `is_numeric` controlla che sia davvero un numero. Tutto il resto (`"abc"`, `null`, un array) → `null` → errore.
- **Multiplo di 0,25**: `fmod($kg * 4, 1) != 0`. `fmod` è il resto della divisione con i decimali. 42,5 × 4 = 170, senza resto, quindi va bene; 42,3 × 4 = 169,2, con resto, quindi è rifiutato.
- **Ripetizioni intere**: `floor($reps) != $reps`. `floor` arrotonda per difetto: se 8,5 arrotondato non è più 8,5, non era intero.
- **`mb_` per le lettere accentate**: in UTF-8 "è" occupa 2 byte. `ucfirst("è")` romperebbe il carattere; `mb_strtoupper(mb_substr(...))` lavora per caratteri, non per byte. Serve l'estensione `mbstring` che hai attivato nel `php.ini`.
- `preg_replace('/\s+/u', ' ', ...)` sostituisce ogni gruppo di spazi con uno solo.
- `array_is_list($raw)` controlla che sia una lista `[0 => ..., 1 => ...]` e non un array associativo.

## Il test: i *data provider*

Invece di scrivere 17 metodi quasi uguali, `validSets()` e `invalidSets()` restituiscono una **tabella di casi**, e l'attributo `#[DataProvider('validSets')]` fa girare il test una volta per riga. Ogni caso ha un nome (`'virgola italiana'`, `'zero ripetizioni'`…): se uno fallisce, PHPUnit ti dice quale.

`expectException(ValidationException::class)` dice: "questo test passa **solo se** viene lanciata quell'eccezione".

## Come in TrackIt

In TrackIt la validazione la fa **Pydantic** in `schemas.py`: dichiari i campi con i tipi (`amount: float`) e FastAPI rifiuta da solo le richieste sbagliate con un 422. Qui non c'è magia: scriviamo noi i controlli, e infatti si vedono tutti.

| GymTracker | TrackIt |
|---|---|
| `SetValidator::sets()` | un modello Pydantic con `Field(ge=0, le=500)` |
| `ValidationException` → 400 | `RequestValidationError` → 422 automatico |
| data provider di PHPUnit | `@pytest.mark.parametrize` |

## In Laravel sarebbe…

Si usano le regole di validazione già pronte, di solito in una **Form Request**:

```php
$request->validate([
    'sets'             => 'required|array|min:1|max:20',
    'sets.*.weight_kg' => 'required|numeric|between:0,500|multiple_of:0.25',
    'sets.*.reps'      => 'required|integer|between:1,100',
]);
```

Se una regola fallisce, Laravel risponde da solo `422` con i messaggi. La virgola italiana invece andrebbe comunque convertita a mano prima.

## Domande da colloquio

1. **Dove validi i dati in input?** Al confine, prima che entrino nella logica e nel database: un unico `SetValidator` usato sia per i dati del client sia per la risposta dell'AI. In più il database ha i suoi vincoli `CHECK`, come ultima difesa.
2. **Perché validi anche l'output dell'AI?** Perché un modello linguistico può sbagliare o inventare: "1000 kg" o "0 ripetizioni". Va trattato come un input non fidato, esattamente come quello dell'utente.
3. **Cos'è un data provider e perché usarlo?** Una funzione che fornisce più casi allo stesso test. Evita copia-incolla e rende facile aggiungere un caso: una riga in più nella tabella.

## Prova per te

Il limite di 20 serie è un po' basso per chi fa esercizi di riscaldamento. Portalo a **30**:
1. nel test, nel caso `'più di 20 serie'`, cambia `21` in `31` (e il nome del caso);
2. aggiungi un caso valido con **30 serie**: devono essere accettate;
3. `composer test` → deve fallire (30 serie oggi sono rifiutate);
4. cambia la costante `MAX_SETS` nel validatore;
5. `composer test` → verde.

Poi rimetti 20 con `git checkout .` (o tieni 30, se ti convince di più: è una scelta di prodotto).
