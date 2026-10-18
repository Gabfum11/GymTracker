<?php
//controlla in automatico che la rotta /healt risponda come deve. Lo fa senza avviare un server vero: costruisce l'app, le passa una richiesta finta e verifica la risposta
declare(strict_types=1);

namespace Tests\Unit;
//unit vuol dire testo unitario, cioè un test che prova un pezzo piccolo del codice
use App\AppFactory; //classe che costruisce l'applicazione, registra le rotte e aggiunge i middleware. Lo usano sia public/index.php (il server vero) sia i test.
use PHPUnit\Framework\TestCase; //strumento standard per scrivere test in php
use Slim\Psr7\Factory\ServerRequestFactory; //classe che costruisce richieste HTTP fittizie, da passare all'app per testarla

final class HealthTest extends TestCase
//significa che HealthTest eredita da TestCase: riceve tutti i suoi metodi, compresi gli assert.
//per convenzione PHPUnit cerca i file che finiscono in Test.php
{
    public function test_health_returns_ok(): void
    //PHPUnit esegue in automatico ogni metodo pubblico il cui nome comincia con test. void vuol dire che il metodo non restituisce niente
    {   
        //costruisce l'app nello stesso modo in cui lo fa index.php
        $app = AppFactory::create();
        //crea una richiesta HTTP finta, come se fosse arrivata dal client. GET /health è la rotta che controlla se il server è acceso
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');
        //handle fa passare la richiesta in tutta l'app: middleware di routing -> trova la rotta healt -> chiama HealthController -> restituisce la risposta. La risposta è finta, non viene inviata a nessuno, ma possiamo leggerla e verificarla.
        $response = $app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(['ok' => true], json_decode((string) $response->getBody(), true));
    }
}
