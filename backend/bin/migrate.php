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

$pdo = Database::connect($_ENV);
$applied = Migrator::run($pdo, __DIR__ . '/../migrations');

echo $applied === [] ? "niente da applicare\n" : 'applicata ' . implode(', ', $applied) . "\n";

// php bin/migrate.php --dev-user → crea l'utente di sviluppo (serve finché non c'è il login)
if (in_array('--dev-user', $argv, true)) {
    $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES ('Sviluppo', 'dev@gymtracker.local', '-')
                   ON CONFLICT (email) DO NOTHING")->execute();
    $id = $pdo->query("SELECT id FROM users WHERE email = 'dev@gymtracker.local'")->fetchColumn();
    echo "utente di sviluppo: id $id (metti DEV_USER_ID=$id nel file .env)\n";
}

// -> chiama un metodo su un oggetto
// :: chiama un metodo sulla classe