<?php

declare(strict_types=1);

namespace App;

use App\Http\HealthController;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

/**
 * Costruisce l'applicazione: registra le rotte e collega le dipendenze a mano.
 * La usano sia public/index.php (il server vero) sia i test.
 */
final class AppFactory
{
    public static function create(array $config = []): App
    {
        $app = SlimAppFactory::create();

        $app->addRoutingMiddleware();
        // in produzione nasconde i dettagli degli errori, in sviluppo li mostra
        $errors = $app->addErrorMiddleware((bool) ($config['debug'] ?? false), true, true);
        // il frontend si aspetta sempre JSON, anche quando qualcosa va storto
        $errors->getDefaultErrorHandler()->forceContentType('application/json');

        $app->get('/health', HealthController::class);

        return $app;
    }
}
