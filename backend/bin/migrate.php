<?php

declare(strict_types=1);

// Uso: php bin/migrate.php — crea o aggiorna le tabelle (come `alembic upgrade head` in TrackIt).

use App\Database;
use App\Migrator;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$applied = Migrator::run(Database::connect($_ENV), __DIR__ . '/../migrations');

echo $applied === [] ? "niente da applicare\n" : 'applicata ' . implode(', ', $applied) . "\n";
