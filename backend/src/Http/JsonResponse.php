<?php
//questa classe ha il solo compito di mettere dei dati php dentro una risposta HTTP come JSON
//esiste perchè Slim non ha una funzione già pronta da per rispondere in JSON. 
//senza questa classe, ogni controller dovrebbe ripetere le stesse tre righe(codifica, JSON, header, stato)
//in questo modo le scrivi una volta sola e ogni controller fa return JsonResponse::with($response, $data, $status) e basta.
declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Scrive un valore PHP come JSON nella risposta.
 * Slim non ha un helper per il JSON (FastAPI lo fa da solo): lo centralizziamo qui.
 */

final class JsonResponse
{
    public static function with(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    //response è la risposta vuota che il controller ha ricevuto da Slim, mixed data accetta qualsiasi cosa: array, numero, stringa, null
    //int statusè il codice HTTP da restituire, se non passato, vale 200 OK.
    {
        //json_encode trasfora l'array PHP in testo JSON: ['ok' => true] diventa {"ok":true}
        //JSON_UNESCAPED_UNICODE lascia le lettere accentate cosi come sono 
        //JSON_THROW_ON_ERROR fa lanciare un'eccezione se la codifica fallisce, invece di restituire false
        //getbody()->write scrive il testo JSON nel corpo della risposta, che Slim poi invia al client.
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}

//il server deve rispondere all'app Flutter. L'app non capisce gli array php, capisce il JSON,un formato di testo che quasi tutti i linguaggi sanno leggere
//JsonResponse fa proprio questa traduzione e la mette nella risposta.
//il contenuto(body) è il testo JSON
//l'etichetta sulla busta(header) dice dentro c'è JSON
//il timbro(status) è un numero che dice com'è andata