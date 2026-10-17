<?php

declare(strict_types=1);

// Punto d'ingresso: il server web manda qui ogni richiesta (come `uvicorn app.main:app` in TrackIt).

use App\AppFactory;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

// legge backend/.env se esiste; online le variabili arrivano da Render
Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$app = AppFactory::create([
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
]);

$app->run();
