# 08 · Record e riepilogo di fine allenamento

## Cosa fa

- **Fine allenamento** apre la schermata **Riepilogo**:
  - in alto la data ("sab 18 ott");
  - tre riquadri: **durata** (`58'`), **serie**, **esercizi**;
  - se ci sono record, il **box arancione** "Nuovo record" / "2 nuovi record", con esercizio e valore;
  - l'elenco degli esercizi con le serie come etichette; la serie record è **arancione**;
  - **Torna all'allenamento** (chiude il riepilogo, l'allenamento resta aperto) e **Salva allenamento** (`POST /workouts/{id}/finish`, poi la chat riparte da "Inizia allenamento").
- Il backend calcola i record e li mette in ogni `WorkoutSummary`: campo `records` e `is_record` sulle serie.

## La regola dei record (`RecordService`)

Esempio con i Dip: allenamento 1 → 40 kg; allenamento 2 → 42,5 e 45; allenamento 3 → 42,5.

| Allenamento | Migliore del giorno | Migliore prima | Record? |
|---|---|---|---|
| 1 | 40 | (nessuno) | no: è il punto di partenza |
| 2 | 45 × 6 | 40 | **sì, uno solo** (la serie migliore, non anche il 42,5) |
| 3 | 42,5 | 45 | no |

Le regole:
1. **Si misura in kg** se l'esercizio ha mai avuto una serie con peso > 0; altrimenti (trazioni senza peso) **in ripetizioni**.
2. **La prima sessione non è un record**, altrimenti ogni esercizio nuovo farebbe "PR".
3. **Record = supera il migliore di tutte le sessioni precedenti**. A parità di peso non è record.
4. **Al massimo un record per esercizio per allenamento**: la serie migliore (più kg; a parità di kg, più ripetizioni).

### Come funziona il codice

1. `SetRepository::historyForUser()` legge **tutte** le serie dell'utente, in ordine di tempo (una query con due `JOIN`).
2. `RecordService::compute()` le raggruppa **esercizio → allenamento → serie** e scorre gli allenamenti ricordandosi il migliore (`$best`).
3. `SummaryService::build()` tiene solo i record dell'allenamento che sta riassumendo e segna `is_record` sulla serie giusta.

`RecordService` è **logica pura**: riceve un array, restituisce un array, niente database. Per questo ha 8 test che non hanno bisogno di Docker e girano in pochi millisecondi.

**Un trucco PHP:** `if ($current > $champion)` confronta due array `[kg, rip]` elemento per elemento: prima i kg, a parità le ripetizioni. È esattamente la regola "più kg, a parità più ripetizioni".

**Una scorciatoia dichiarata:** rileggere tutto lo storico a ogni riepilogo va benissimo per centinaia di allenamenti. Con migliaia converrebbe salvare i record in una tabella. È scritto nel codice con un commento `shortcut:`.

## Flutter

- **`SummaryView`** (`features/summary/summary_view.dart`) è solo il contenuto (riquadri, record, esercizi). Lo riusiamo nel passo 12 per il foglio dello Storico: **scritto una volta, usato due volte**.
- **`SummaryScreen`** aggiunge titolo, data e i due pulsanti.
- **`Navigator.push(...)`** apre una schermata sopra l'altra; **`Navigator.pop`** la chiude e torna indietro, come la freccia "indietro" del telefono.
- **`ChatViewModel.currentSummary()`** legge il riepilogo aggiornato; **`finish()`** chiude l'allenamento e ricarica la chat.
- **`formatDay`**: "sab 17 ott", con le abbreviazioni scritte a mano, per non aggiungere una libreria solo per questo.
- L'**arancione** compare solo qui: box record ed etichette record. È la regola del progetto.

## Come in TrackIt

| GymTracker | TrackIt |
|---|---|
| record calcolati al volo da `RecordService` | statistiche calcolate al volo dalle spese |
| `SummaryView` riusato in due schermate | componenti riusati tra Home e Statistiche |
| `Navigator.push` / `pop` | `router.push` / `router.back` di Expo Router |

## In Laravel sarebbe…

`RecordService` resterebbe quasi identico, perché è PHP puro senza dipendenze. La query dello storico con Eloquent: `Set::with(['workout', 'exercise'])->whereHas('workout', fn ($q) => $q->where('user_id', $id))->get()`.

## Domande da colloquio

1. **Perché non salvi i record nel database?** Perché si possono ricavare dalle serie: salvarli vorrebbe dire tenerli allineati a ogni modifica. Calcolarli è più semplice e sempre corretto; se un giorno diventa lento, li salverei in una tabella aggiornata a ogni salvataggio.
2. **Come hai testato la regola dei record?** Con test unitari su dati in memoria: un caso per regola (prima sessione, due serie migliori nello stesso giorno, parità, corpo libero, passaggio ai kg, più esercizi). In più un test d'integrazione che fa due allenamenti veri via API.
3. **Perché `SummaryView` è separato da `SummaryScreen`?** Il contenuto serve in due posti (fine allenamento e Storico) con contorni diversi. Separarli evita di copiare il codice.

## Prova per te

Oggi, a parità di kg, più ripetizioni **non** sono un record (45 × 8 dopo 45 × 6). Molti la considererebbero un record. Prova a cambiare la regola:
1. in `RecordServiceTest` scrivi `test_same_weight_more_reps_is_a_record`: allenamento 1 `[[45, 6]]`, allenamento 2 `[[45, 8]]` → ti aspetti **1** record;
2. `composer test` → fallisce;
3. in `RecordService::compute()`, invece di confrontare solo `$value`, confronta l'array `[kg, rip]` della serie migliore con quello del migliore precedente (come fa già `bestSetIndex`);
4. `composer test` → controlla anche che `test_equal_weight_is_not_a_record` ora fallisca: va aggiornato, perché la regola è cambiata.

È una decisione di prodotto: se ti convince tienila, altrimenti `git checkout .`.
