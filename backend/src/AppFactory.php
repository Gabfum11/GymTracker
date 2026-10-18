<?php
#questo file costruisce l'applicazione, registra le rotte e aggiunge i middleware. Lo usano sia public/index.php (il server vero) sia i test.
declare(strict_types=1);

namespace App; #dichiara che questa classe vive nel namespace APP, quindi il suo Nome completo è App\AppFactory. 
#gli import. l'ultimo è un alias, perché AppFactory è già il nome della classe che stiamo scrivendo.
use App\Http\HealthController; 
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;
use App\Http\VersionController;

/**
 * Costruisce l'applicazione: registra le rotte e collega le dipendenze a mano.
 * La usano sia public/index.php (il server vero) sia i test.
 */
final class AppFactory
#si chiama factory perchè è una funzione il cui unico compito è costruire un oggetto complicato e restituirlo pronto all'uso.
#final vuol dire che nessuno può creare una sottoclasse di AppFactory dice a chi legge " questa classe è completa così"
{
    public static function create(array $config = []): App #public indica che si può chiamare da altri file, sulla classe, senza creare un oggetto con new (static)
    #app è il tipo di ritorno, cioè restituisce un'app Slim. Il tipo di ritorno è opzionale, ma aiuta a capire cosa fa la funzione e permette a PHP di controllare che il valore restituito sia del tipo giusto.
    {
        $app = SlimAppFactory::create(); #crea l'app vuota di Slim

        $app->addRoutingMiddleware(); #aggiunge il middleware che guarda metodo e indirizzo della richiesta (GET/health) e sceglie quale controller chiamare.
        // in produzione nasconde i dettagli degli errori, in sviluppo li mostra
        $errors = $app->addErrorMiddleware((bool) ($config['debug'] ?? false), true, true);
        //aggiunge il middleware che intercetta gli errori, se qualcosa va storto, invece di una pagina bianca risponde con un errore ordinato
        // il frontend si aspetta sempre JSON perchè l'app flutter se lo aspetta quindi forziamo sempre in questo modo
        $errors->getDefaultErrorHandler()->forceContentType('application/json');


        $app->get('/health', HealthController::class);
        //registra la rotta healt, ovvero un controllo di salute dell'applicazione che risponde con 200 OK se l'app è accesa.
        $app->get('/version', VersionController::class);
        //registra la rotta version, che risponde con il numero di versione dell'app
        return $app; 
    }
}
