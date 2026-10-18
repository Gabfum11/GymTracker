<?php

declare(strict_types=1); #attiva i tipi rigidi. Senza, se una funzione vuole un int e le passi "5", PHP lo converte di nascosto.
#con questa riga dà errore. 
// Punto d'ingresso del backend: ogni richiesta HTTP che arriva al server passa da qui.


use App\AppFactory; #app perchè
use App\Ai\GroqClient;
use App\Database;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php'; #il punto unisce le strighe, carica il file autoload.php generato da composer, che a sua volta carica tutte le classi del progetto e delle librerie esterne.
#l'autoloader è un meccanismo che permette di caricare automaticamente le classi PHP quando vengono utilizzate, senza doverle includere manualmente con require o include. Questo rende il codice più pulito e gestibile, soprattutto in progetti di grandi dimensioni.
// legge backend/.env se esiste; online le variabili arrivano da Render
Dotenv::createImmutable(__DIR__ . '/..')->safeLoad(); #legge le variabili d'ambiente dal file .env e mette i valori in  $_ENV
#createImmutable : se una variabile esiste gia in $_ENV non la sovrascrive. safeLoad: se il file .env non esiste non da errore.
$app = AppFactory::create([ # :: chiama un metodo statico, cioè di una classe e non di un oggetto.
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true', #se la variabile d'ambiente APP_DEBUG è true, allora il debug è attivo. Se non esiste, il default è false
    'dev_user_id' => (int) ($_ENV['DEV_USER_ID'] ?? 1), // utente fisso finché non c'è il login (passo 9)
    'cors_origin' => $_ENV['CORS_ORIGIN'] ?? '*', // online: l'indirizzo di GitHub Pages
], Database::connect($_ENV), empty($_ENV['GROQ_API_KEY']) ? null : new GroqClient($_ENV['GROQ_API_KEY'], $_ENV['GROQ_MODEL'] ?? 'openai/gpt-oss-20b'));

$app->run();
#legge la richiesta arrivata, la fa passare per i middleware e il controller giusto
#la cartella si chiama public perché è l'unica accessibile dall'esterno. Tutto il resto del backend non è accessibile direttamente, ma solo tramite questo file.