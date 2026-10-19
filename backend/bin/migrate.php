<?php
//script che lanci a mano per creare o aggiornare le tabelle del db

declare(strict_types=1);

// Uso: php bin/migrate.php — crea o aggiorna le tabelle (come `alembic upgrade head` in TrackIt).

use App\Database;
use App\Migrator;
use Dotenv\Dotenv;


require __DIR__ . '/../vendor/autoload.php'; //carica il file autoload.php dopo questa riga php sa trovare da solo tutte le classi del progetto e delle librerie installate
//chiama un metodo statico sulla classe Dotenv e ottiene un oggetto
Dotenv::createImmutable(__DIR__ . '/..')->safeLoad(); //chiama safeLoad su quell'oggetto (legge il .env)

$applied = Migrator::run(Database::connect($_ENV), __DIR__ . '/../migrations');

echo $applied === [] ? "niente da applicare\n" : 'applicata ' . implode(', ', $applied) . "\n";

// -> chiama un metodo su un oggetto
// :: chiama un metodo sulla classe