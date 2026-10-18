<?php
#questo file costruisce l'applicazione, registra le rotte e aggiunge i middleware. Lo usano sia public/index.php (il server vero) sia i test.
declare(strict_types=1);

namespace App; #dichiara che questa classe vive nel namespace APP, quindi il suo Nome completo è App\AppFactory. 
#gli import. l'ultimo è un alias, perché AppFactory è già il nome della classe che stiamo scrivendo.
use App\Ai\LlmClient;
use App\Ai\LlmException;
use App\Http\CorsMiddleware;
use App\Http\ExerciseController;
use App\Http\ParseController;
use App\Service\ParserService;
use App\Http\HealthController;
use App\Http\JsonResponse;
use App\Http\WorkoutController;
use App\Repository\ExerciseRepository;
use App\Repository\SetRepository;
use App\Repository\WorkoutRepository;
use App\Service\ConflictException;
use App\Service\NotFoundException;
use App\Service\ValidationException;
use App\Service\WorkoutService;
use PDO;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;
use Slim\Routing\RouteCollectorProxy;
use App\Http\VersionController;

/**
 * Costruisce l'applicazione: registra le rotte e collega le dipendenze a mano.
 * La usano sia public/index.php (il server vero) sia i test.
 */
final class AppFactory
#si chiama factory perchè è una funzione il cui unico compito è costruire un oggetto complicato e restituirlo pronto all'uso.
#final vuol dire che nessuno può creare una sottoclasse di AppFactory dice a chi legge " questa classe è completa così"
{
    public static function create(array $config = [], ?PDO $pdo = null, ?LlmClient $llm = null): App #public indica che si può chiamare da altri file, sulla classe, senza creare un oggetto con new (static)
    #app è il tipo di ritorno, cioè restituisce un'app Slim. Il tipo di ritorno è opzionale, ma aiuta a capire cosa fa la funzione e permette a PHP di controllare che il valore restituito sia del tipo giusto.
    {
        $app = SlimAppFactory::create(); #crea l'app vuota di Slim

        // legge il corpo JSON delle richieste: poi $request->getParsedBody() restituisce un array
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware(); #aggiunge il middleware che guarda metodo e indirizzo della richiesta (GET/health) e sceglie quale controller chiamare.
        // in produzione nasconde i dettagli degli errori, in sviluppo li mostra
        $errors = $app->addErrorMiddleware((bool) ($config['debug'] ?? false), true, true);
        //aggiunge il middleware che intercetta gli errori, se qualcosa va storto, invece di una pagina bianca risponde con un errore ordinato
        // il frontend si aspetta sempre JSON perchè l'app flutter se lo aspetta quindi forziamo sempre in questo modo
        $errors->getDefaultErrorHandler()->forceContentType('application/json');
        // le nostre eccezioni diventano codici HTTP precisi, con il messaggio in italiano
        foreach ([ValidationException::class => 400, NotFoundException::class => 404, ConflictException::class => 409] as $class => $status) {
            $errors->setErrorHandler($class, function (ServerRequestInterface $request, \Throwable $e) use ($app, $status) {
                return JsonResponse::with($app->getResponseFactory()->createResponse(), ['error' => $e->getMessage()], $status);
            });
        }
        // l'AI non risponde: messaggio fisso, i dettagli tecnici (che possono contenere la risposta di Groq) restano nel log
        $errors->setErrorHandler(LlmException::class, function (ServerRequestInterface $request, \Throwable $e) use ($app) {
            error_log('LLM: ' . $e->getMessage());

            return JsonResponse::with($app->getResponseFactory()->createResponse(), ['error' => 'Il bot non risponde, riprova tra poco'], 503);
        });


        $app->get('/health', HealthController::class);
        //registra la rotta healt, ovvero un controllo di salute dell'applicazione che risponde con 200 OK se l'app è accesa.
        $app->get('/version', VersionController::class);
        //registra la rotta version, che risponde con il numero di versione dell'app

        if ($pdo !== null) {
            self::addApiRoutes($app, $pdo, $config, $llm);
        }

        // aggiunto per ultimo = strato più esterno: mette gli header CORS anche sulle risposte di errore
        $app->add(new CorsMiddleware($app->getResponseFactory(), $config['cors_origin'] ?? '*'));

        return $app;
    }

    /** Le rotte che usano il database. Le dipendenze si creano qui, a mano, dal basso verso l'alto. */
    private static function addApiRoutes(App $app, PDO $pdo, array $config, ?LlmClient $llm): void
    {
        $exerciseRepo = new ExerciseRepository($pdo);
        $workouts = new WorkoutController(new WorkoutService(new WorkoutRepository($pdo), $exerciseRepo, new SetRepository($pdo)));
        $exercises = new ExerciseController($exerciseRepo);
        $parse = $llm ? new ParseController(new ParserService($llm), $exerciseRepo) : null;

        $app->group('', function (RouteCollectorProxy $api) use ($workouts, $exercises, $parse) {
            if ($parse) { // senza chiave AI (es. alcuni test) la rotta semplicemente non c'è
                $api->post('/parse', $parse);
            }
            $api->get('/exercises', [$exercises, 'list']);
            $api->post('/exercises', [$exercises, 'create']);
            $api->post('/workouts', [$workouts, 'start']);
            $api->get('/workouts/current', [$workouts, 'current']);
            $api->put('/workouts/{id:\d+}/exercises/{exerciseId:\d+}/sets', [$workouts, 'saveSets']);
            $api->post('/workouts/{id:\d+}/finish', [$workouts, 'finish']);
        })->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($config) {
            // shortcut: utente fisso finché non c'è il login (passo 9), poi qui arriverà l'AuthMiddleware
            return $handler->handle($request->withAttribute('user_id', (int) $config['dev_user_id']));
        });
    }
}
