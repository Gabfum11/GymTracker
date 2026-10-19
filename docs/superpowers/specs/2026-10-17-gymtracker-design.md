# GymTracker — documento di progetto

Data: 2026-10-17 · Mockup: https://claude.ai/artifact/9pcwvcKMuGcWtnfdAmGxu7 (versione 6)

Le parti marcate **[scelta del builder]** sono decisioni prese da Claude durante il brainstorming, non dall'utente: vanno bene, ma l'utente può cambiarle.

## 1. Perché esiste

Progetto da portare al colloquio per **Junior Full Stack Developer – Flutter / PHP** presso Vector S.r.l. Ai candidati è stato chiesto di portare un progetto a scelta. L'annuncio chiede Flutter/Dart, PHP 8 + API REST, SQL, Git, e cita come plus: integrazione AI con output JSON strutturato, autenticazione a token, state management, debugging full-stack, deploy.

Vincoli che governano tutto:

- **Due settimane** di tempo.
- L'utente **parte da zero** su Flutter e su PHP, ma ha già costruito **TrackIt** (`D:\FinanceApp`): app di finanze con assistente AI che trasforma una frase ("ieri 12€ pizza") in una spesa. Stack: Python/FastAPI (router + business_logic), SQLAlchemy/Alembic, pytest, JWT, React Native/Expo; PostgreSQL su Supabase, backend su Render, frontend su GitHub Pages, AI via Groq (`openai/gpt-oss-*` con `response_format: json_object`). Conseguenze: spiegare PHP/Flutter **per analogia** con FastAPI/React Native; riusare lo schema di deploy già collaudato.
- **Il codice lo scrive Claude, l'utente lo studia passo per passo.** Al colloquio dovrà spiegarlo e probabilmente modificarlo dal vivo. Il codice deve quindi essere semplice, da manuale, comprensibile; una soluzione furba ma difficile da spiegare è sbagliata.
- La demo si fa **dal telefono** dell'utente; l'utente porta anche il portatile.

## 2. Cos'è

Una web app per registrare gli allenamenti in palestra **scrivendo in una chat**. L'utente scrive come gli viene ("dip 45kg 8", "45x8, 45x6, 40x8", "la prima 45 per 8, poi 45 per 6, l'ultima 40 per 8") e il bot lo trasforma in dati ordinati: esercizio, serie, kg, ripetizioni.

Il punto distintivo: **il bot si adatta all'utente, non il contrario.** La chat esiste perché molti non sanno come "si registra" un esercizio.

## 3. Obiettivi, non-obiettivi, anti-obiettivi

**Obiettivi**
- Registrare un allenamento intero dalla chat, dal telefono, con poche battute.
- Rivedere gli allenamenti passati (Storico) e la crescita per esercizio (Progressi).
- Funzionare online da un link pubblico, su telefono e su computer.
- Mostrare al colloquio le tecnologie dell'annuncio usate in modo pulito.

**Non-obiettivi (fuori dalle due settimane)**
- Timer di recupero, schede/programmi predefiniti, funzioni social.
- Obiettivi settimanali, confronto con periodi precedenti.
- Peso corporeo, altezza, età, calorie.
- Volume totale (kg × rip sommati): scartato dall'utente perché "non indica nulla".
- Login social (Google).
- App Android nativa (solo se avanza tempo alla fine).

**Anti-obiettivi (falliscono anche se funzionano)**
- Codice che l'utente non sa spiegare.
- Il bot che salva dati sbagliati senza conferma: **nulla viene salvato senza che l'utente abbia visto e confermato la scheda**.
- Perdere un allenamento a metà se si chiude il browser.
- Una demo che dipende dal Wi-Fi dell'azienda o dal portatile.
- La chiave dell'API AI nel codice del frontend o su GitHub.

## 4. Schermate e flussi

Stile visivo: vedi §8. Tutte le schermate sono pensate prima per il telefono (colonna singola, pulsanti grandi in basso, raggiungibili col pollice) e si allargano sul computer.

Barra di navigazione in basso, 4 voci con icona: **Allena · Storico · Progressi · Profilo**. La voce attiva ha una "pillola" verde dietro l'icona.

Prima di tutto ci sono **registrazione e login** (nome, email, password). Mockup approvato: https://claude.ai/artifact/7WpwVpv3X3agSgxPoYiVyT (versione 2).

- **Benvenuto** (deciso dall'utente): schermata a parte con il nome GymTracker, la frase **"Crea i tuoi allenamenti personalizzati"**, un esempio di conversazione con il bot (domanda → messaggio → scheda con le serie → "2 serie salvate") e il pulsante **Continua** → Accesso. [scelta del builder: compare solo la prima volta su quel dispositivo]
- **Accesso**: email, password con Mostra/Nascondi, "Password dimenticata?", Accedi, link "Registrati". **Niente pulsante "account demo"** (tolto dall'utente; un account con dati d'esempio per la demo è da decidere più avanti).
- **Registrazione** (nome, email, password "almeno 8 caratteri") → **Codice** (6 caselle, `autocomplete="one-time-code"`, "rimanda il codice") → app.
- **Password dimenticata** (email) → **Codice** → **Nuova password** (avvisa che si viene disconnessi da tutti i dispositivi) → Accesso con "Password cambiata. Ora accedi."
- Errori dei form in **rosso** (nuovo colore, solo per gli errori; l'arancione resta per i record).

### 4.1 Allena (chat)

1. All'apertura, se non c'è un allenamento in corso: breve spiegazione di come funziona + pulsante **Inizia allenamento**.
2. Premuto "Inizia", parte l'allenamento (ora di inizio salvata). Da qui restano **sempre visibili in basso**: **Prossimo esercizio** e **Fine allenamento**, più il campo di testo.
3. **Prossimo esercizio** → il bot chiede "Che esercizio stai facendo?". L'utente scrive (es. "dip con zavorra").
4. Il bot chiede di scrivere le serie "come ti viene, anche tutte insieme". L'utente scrive.
5. Il bot risponde con una **scheda di conferma** nella chat: nome esercizio + una riga per serie (n°, kg, rip).
   - Ogni numero si **tocca e si modifica** (tastiera numerica).
   - **×** su ogni riga elimina la serie.
   - **+ Serie** aggiunge una riga copiando l'ultima e mette il cursore sulle ripetizioni.
   - **Conferma** salva le serie. La scheda diventa "3 serie salvate · Modifica"; "Modifica" la riapre.
6. **Scorciatoia**: se l'utente scrive tutto in un messaggio ("dip 45kg 8, 45x6") senza passare da "Prossimo esercizio", il bot deve capirlo e mostrare direttamente la scheda.
7. Se il messaggio è ambiguo il bot **non indovina**: fa una domanda precisa ("8 sono le ripetizioni o i kg?").
8. **Fine allenamento** → schermata Riepilogo (4.2).

**Nomi degli esercizi coerenti (critico).** "dip", "dips", "parallele" devono finire sullo stesso esercizio, altrimenti Progressi si spezza. Quando il nome scritto somiglia a un esercizio che l'utente ha già usato, il bot propone quello esistente ("Intendi Dip zavorrati?") e l'utente conferma o sceglie di crearne uno nuovo. [scelta del builder: la somiglianza la valuta l'AI ricevendo l'elenco degli esercizi esistenti dell'utente; niente catalogo globale di esercizi]

**Peso aggiunto.** Per esercizi a corpo libero si salva solo il peso aggiunto: "dip senza peso" = 0 kg, "dip +10" = 10 kg. Negli esercizi con zavorra il peso si mostra col "+" (es. "+45 kg × 8"). [scelta del builder: un esercizio è "a zavorra" se il nome lo indica o se l'utente ha scritto "+"; salvato come flag sull'esercizio]

**Nessuna perdita di dati.** Ogni conferma salva subito sul server. Se l'utente chiude e riapre l'app con un allenamento in corso, ritrova la chat dell'allenamento in corso. [scelta del builder: allenamento "in corso" = ha `started_at` e non ha `ended_at`; la cronologia dei messaggi della chat **non** si salva, si ricostruisce mostrando le schede già confermate]

### 4.2 Riepilogo (fine allenamento)

- Data in alto.
- Tre riquadri: **durata**, **serie**, **esercizi**.
- Box arancione **Nuovo record / N nuovi record** se ce ne sono (vedi §5.3), con esercizio e valore.
- Elenco esercizi: nome, numero di serie, e le serie come etichette "45 kg × 8"; la serie da record è in arancione.
- Pulsanti: **Torna all'allenamento** (riapre la chat, l'allenamento resta aperto) e **Salva allenamento** (chiude l'allenamento: `ended_at`). [scelta del builder: il mockup diceva "Modifica"; rinominato perché le serie si modificano già nella chat]

### 4.3 Storico

- Titolo + "N allenamenti". Raggruppato per mese ("OTTOBRE 2026"), dal più recente.
- Una scheda per allenamento: a sinistra giorno della settimana + numero (SAB / 17); titolo; riga "1:08 · 4 esercizi · 16 serie"; elenco nomi esercizi (troncato su una riga); badge arancione "2 PR" se ci sono record.
- Tocco sulla scheda → sale dal basso un **foglio con lo stesso riepilogo di 4.2** (senza i pulsanti di salvataggio). Si chiude con ×, tocco fuori o Esc.
- **Titolo dell'allenamento** [scelta del builder]: generato automaticamente dai primi due esercizi ("Dip zavorrati & Panca piana"). Nessuna classificazione Push/Pull/Gambe e nessun campo da compilare.

### 4.4 Progressi

- Menu a tendina **Esercizio** con gli esercizi dell'utente.
- Grande il **carico migliore** di sempre per quell'esercizio + "+N kg" rispetto al primo dato.
- **Grafico a linea**: un punto per ogni allenamento in cui l'esercizio compare, valore = peso più alto di quel giorno. [scelta del builder: punti per allenamento, non per settimana come nel mockup; più semplice e onesto]
- Sotto: elenco delle ultime sessioni con il loro miglior carico.

### 4.5 Profilo

- Nome ed email.
- **Calendario degli ultimi 3 mesi**: 13 colonne (settimane) × 7 righe (lun–dom), quadratino verde = giorno con allenamento, grigio = riposo, bordo = oggi. Mesi sopra, legenda e totale sotto. L'utente lo considera "un ottimo indicatore di costanza".
- **Ultimi record**: i 3 più recenti, con esercizio, data e valore in arancione.
- **Esci**.
- Esplicitamente **tolti** dall'utente: peso corporeo, media allenamenti settimanali, esercizi più fatti, settimane di fila.

## 5. Dati e regole

### 5.1 Tabelle [scelta del builder: forma indicativa]

- `users` (id, name, email unico, password_hash, created_at)
- `users` + `is_verified`, `token_version`; `refresh_tokens` (id, user_id, token_hash, expires_at, used_at, created_at); `otp_codes` (id, user_id, code_hash, purpose, expires_at, attempts, created_at); `rate_limits` (bucket, count, reset_at) — vedi §6.3. La prima versione aveva `auth_tokens`, sostituita da `refresh_tokens`.
- `exercises` (id, user_id, name, is_weighted_bodyweight, created_at) — esercizi **per utente**, nome unico per utente
- `workouts` (id, user_id, started_at, ended_at nullable)
- `sets` (id, workout_id, exercise_id, position, weight_kg NUMERIC(5,2), reps INT, created_at)

Record, titoli, durata, calendario si **calcolano** dalle tabelle, non si salvano.

### 5.2 Validazione

Tutto ciò che arriva dal client **e tutto ciò che arriva dall'AI** viene validato in PHP prima di salvare: kg tra 0 e 500 (multipli di 0,25 ammessi), rip intere tra 1 e 100, nome esercizio non vuoto e ≤ 60 caratteri. Query SQL solo con prepared statements (PDO).

### 5.3 Record [scelta del builder]

Una serie è un **record** se il suo `weight_kg` è maggiore di ogni serie precedente dello stesso esercizio dello stesso utente. Solo "carico massimo", nessun massimale stimato (e1RM) né "5RM": l'utente ha preferito termini comprensibili a un principiante. Il primo allenamento in assoluto di un esercizio non conta come record (altrimenti ogni esercizio nuovo farebbe "PR").

- **Al massimo un record per esercizio per allenamento** [scelta del builder]: se il precedente massimo era 40 kg e oggi fai 42,5 e poi 45, il record è uno solo, la serie migliore (45). Il conteggio "N nuovi record" e il badge "N PR" contano quindi gli esercizi con record.
- **Esercizi a corpo libero senza peso** [scelta del builder]: se per un esercizio l'utente non ha mai usato peso (tutte le serie a 0 kg), record e grafico di Progressi usano le **ripetizioni massime in una serie** al posto dei kg. Appena compare una serie con peso, si passa ai kg.

## 6. Architettura e stack

### 6.1 Stack (deciso con l'utente: le tecnologie dell'annuncio)

- **Frontend: Flutter Web**, responsive (telefono + computer). Stesso codice compilabile per Android, ma la build Android è un extra finale.
- **Backend: PHP 8.x + Slim 4** (micro-framework, scelto al posto di Laravel per non perdere tempo in configurazione e per far capire bene routing e HTTP).
- **Database: PostgreSQL su Supabase** (deciso dall'utente, che lo usa già per TrackIt). Supabase si usa **solo come database**: niente Supabase Auth né API automatiche, altrimenti il backend PHP perde senso. Connessione da PHP con PDO (`pdo_pgsql`) tramite la stringa **Session pooler** (la connessione diretta è solo IPv6). In locale: PostgreSQL. Piano gratuito: il progetto va in pausa dopo 7 giorni di poca attività; si riattiva dal pannello.
- **AI: Groq** (deciso dall'utente; piano gratuito, API compatibile con OpenAI), chiamato **solo dal backend**, con output JSON. [scelta del builder: punto di partenza `openai/gpt-oss-20b` con `response_format: json_object`, come in TrackIt; verificare nel piano che sia ancora disponibile. I limiti del piano gratuito si gestiscono mostrando un messaggio chiaro quando vengono superati]
- **Git** dall'inizio, repository su GitHub.

### 6.2 Architettura (approvata dall'utente)

**Backend a strati** (MVC senza la V, perché le API rispondono JSON):

```
HTTP → Controller → Service → Repository → PostgreSQL
```

- Controller: legge la richiesta, valida, risponde JSON. Nessuna logica.
- Service: regole dell'app (record, chiusura allenamento, riconoscimento esercizi).
- Repository: unico posto con SQL.
- **ParserService**: isola l'AI. Input: testo dell'utente + contesto (esercizio corrente, esercizi esistenti dell'utente). Output: JSON validato. Testabile da solo; cambiare fornitore AI tocca un solo file.

**Frontend MVVM leggero:**

```
Schermata (widget) → ViewModel (ChangeNotifier) → ApiClient (HTTP)
```

Niente Riverpod all'inizio: si usa `ChangeNotifier`, già incluso in Flutter. Riverpod può arrivare alla fine come refactoring (è anche una buona storia per il colloquio).

**Esplicitamente evitati**: Clean Architecture completa, DDD, microservizi.

### 6.3 Autenticazione (decisa dall'utente il 2026-10-19: "alla stessa maniera di TrackIt")

Stesso schema di `D:\FinanceApp` (`business_logic/security.py`, `routers/auth.py`), rifatto in PHP:

- **Password** con Argon2id (`password_hash(..., PASSWORD_ARGON2ID)`).
- **Access token JWT** HS256 (libreria `firebase/php-jwt`), valido **15 minuti**, payload `sub` (id utente) + `ver` (`users.token_version`). Inviato come `Authorization: Bearer`.
- **Refresh token** casuale, salvato solo come **SHA-256** in `refresh_tokens`, valido **30 giorni**, **ruotato** a ogni uso (`used_at`). Se torna un refresh già usato = copiato → revoca di tutti i refresh e `token_version + 1`.
- **Verifica email con codice OTP** a 6 cifre (generatore crittografico), valido 10 minuti, max 5 tentativi per codice, max 3 invii all'ora per account. Senza verifica non si fa login.
- **Recupero password**: codice OTP via email → token JWT di reset (`purpose=password_reset`, breve) → nuova password; poi `token_version + 1` e revoca dei refresh (tutte le sessioni chiuse).
- **Logout** (revoca il refresh del dispositivo) e **logout da tutti i dispositivi**.
- **Limiti per IP** su registrazione e login.
- **Email via Brevo** (API HTTP, come TrackIt: Render gratuito blocca SMTP). Senza chiave Brevo (sviluppo, test) il codice si scrive nel log del server.
- Escluso: login con Google.
- Ogni endpoint protetto restituisce solo i dati dell'utente del token.

### 6.4 Formato del parser AI [scelta del builder: forma indicativa]

Il ParserService restituisce uno di tre esiti:

```json
{ "type": "sets", "exercise": "Dip zavorrati", "matched_existing_id": 12, "sets": [{"weight_kg": 45, "reps": 8}, {"weight_kg": 45, "reps": 6}] }
{ "type": "exercise", "exercise": "Dip zavorrati", "matched_existing_id": null }
{ "type": "question", "text": "8 sono le ripetizioni o i kg?" }
```

La risposta dell'AI non viene mai salvata direttamente: diventa la scheda di conferma, e si salva solo ciò che l'utente conferma.

## 7. Demo e pubblicazione

- Sviluppo in locale sul portatile (PHP + PostgreSQL locali).
- **Online entro la fine della prima settimana**, tutto su piani gratuiti, con lo stesso schema già collaudato in TrackIt:
  - **Flutter Web** → **Render Static Site** (deciso dall'utente il 2026-10-19 al posto di GitHub Pages: tutto su Render). Render non ha Flutter: lo script di build lo scarica e lancia `flutter build web`. Sempre attivo, ripubblicato a ogni push.
  - **API PHP** → Render *Web Service* con **Dockerfile** (Render non ha un runtime PHP nativo). Il piano gratuito si spegne dopo ~15 minuti di inattività e la prima richiesta può impiegare fino a un minuto: prima della demo si apre l'app un paio di minuti prima. Il frontend mostra un messaggio "Sto svegliando il server…" se la prima risposta tarda. [scelta del builder]
  - **Ore gratuite di Render** (750 al mese per account, poi TUTTI i Web Service gratuiti sospesi fino al mese dopo): l'utente ha già TrackIt su Render tenuto sveglio da un cron. Deciso il 2026-10-19: **niente cron per GymTracker**; il cron di TrackIt su cron-job.org ridotto a **ogni 10 minuti dalle 7:00 alle 23:50 (Europe/Rome)** ≈ 535 h/mese + GymTracker ≈ 95 h = ~630 su 750. Controllare l'uso a metà mese.
  - **Database** → Supabase (vedi §6.1). Scartati il Postgres gratuito di Render (scade dopo 30 giorni) e Aiven MySQL (strumento nuovo per l'utente).
  - Scartati gli hosting PHP gratuiti tipo InfinityFree: bloccano le richieste API verso il sito con un controllo JavaScript non disattivabile.
  - Frontend e API su domini diversi → CORS da configurare sull'API.
- **Account con allenamenti d'esempio** per la demo: da decidere più avanti (l'utente ha tolto il pulsante "account demo" dall'accesso).
- **Piano B**: la stessa app in locale sul portatile.
- Chiave API solo in variabili d'ambiente / file di configurazione escluso da Git.

## 8. Stile visivo (dal mockup, approvato)

- Tema **scuro** ("Sala pesi"): sfondo quasi nero leggermente bluastro, superfici un po' più chiare, bordi sottili.
- **Verde lime** = azioni (Conferma, voce attiva, linea del grafico, giorni nel calendario). **Arancione** = solo record.
- Font: **Barlow Condensed** per titoli e numeri, **Barlow** per il testo.
- Numeri grandi dove contano (kg, rip). Pulsanti alti almeno 48 px. Contrasto leggibile.
- Il mockup HTML è il riferimento visivo; in Flutter si ricrea con un `ThemeData` scuro e i colori come costanti.

## 9. Come lavoriamo (approvato dall'utente)

- **Un passo alla volta, ogni passo funzionante** (es. giorno 1: API PHP che risponde; poi salvataggio di una serie; ...).
- **Un commit per passo**, messaggio chiaro: la cronologia Git è il percorso di studio e una prova di lavoro ordinato.
- **Appunti per ogni passo** in `docs/appunti/`: cosa fa, perché è fatto così, cosa potrebbero chiedere al colloquio.
- **Una prova per l'utente per ogni passo**: una piccola modifica da fare da solo (es. "aggiungi il campo note alla serie"), per allenarsi alle modifiche dal vivo.
- Test: PHPUnit sui Service e sulla validazione dell'output del parser; pochi test Flutter dove c'è logica nel ViewModel.

## 10. Priorità

**Obiettivo dell'utente: realizzare tutte le funzionalità di questo documento**, punti 1–8 compresi "L'ultima volta" e la correzione via chat. Le ore al giorno non sono fissate: il piano va costruito per arrivare a tutto, con un controllo dell'avanzamento ogni giorno, così un ritardo si vede presto. Solo build Android e Riverpod sono veri extra. Se il tempo finisce, si taglia dal fondo.

1. Chat che registra un esercizio: testo → AI → scheda di conferma modificabile → salvataggio. Fino al login si lavora con un utente fisso, **solo in locale**.
2. Inizio/fine allenamento + Riepilogo.
3. Registrazione con verifica email, login, JWT + refresh, recupero password (§6.3). [scelta del builder: anticipato rispetto alla prima versione perché l'app va online in settimana 1 e un endpoint AI pubblico senza login consumerebbe credito a chiunque abbia il link]
4. Deploy online + dati demo, **entro la fine della settimana 1**, con limite di chiamate al parser per utente (es. 200 al giorno) [scelta del builder], perché le credenziali demo sono pubbliche nel README.
5. Storico con foglio di dettaglio.
6. Progressi con grafico.
7. Profilo (calendario + ultimi record).
8. Rifiniture: "L'ultima volta: 45 × 8…" sotto il nome dell'esercizio, correzione via chat ("la seconda erano 7"), build Android, Riverpod.

## 11. Lasciato al builder

Struttura delle cartelle, nomi di classi e file, libreria del grafico in Flutter, modello Groq esatto, testo dei messaggi del bot, gestione CORS, schema SQL definitivo (partendo da §5.1).
