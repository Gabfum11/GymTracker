<?php
//un controller è la classe che decide cosa rispondere a una richiesta HTTP.

declare(strict_types=1); //php smette di convertire i tipi da solo, se una funzione vuole un int e le passi "5" ottieni un errore

namespace App\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
//importa due interfacce psr-7, lo standard php per descrivere richieste e risposte HTTP.

/** GET /health: risponde se il server è acceso. Utile anche per svegliare Render. */
//su render il server va in pausa se nessuno lo usa per 15 minuti
final class HealthController
{
    #invoke è un metodo che permette di usare un oggetto come se fosse una funzione. Slim crea new healthController() e poi lo chiama direttamente
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    //request è la richiesta in arrivo, qui non viene usata, ma Slim la passa sempre
    //response è una risposta vuota che slim prepara e passa al controller, che la riempie e la restituisce. Slim poi la invia al client.
    {
        return JsonResponse::with($response, ['ok' => true]);
        //passa l'array ['ok' => true] a JsonResponse::with, che lo trasforma in JSON e lo mette nel corpo della risposta, con codice 200 OK e header Content-Type: application/json
    }
}


/** il namespace serve a evitare conflitti di nomi e far trovare i file in automatico 
     * Nel progetto ci sono due classi di nome AppFactory: App\AppFactory e Slim\Factory\AppFactory. Per distinguerle si usano i namespace.
*/