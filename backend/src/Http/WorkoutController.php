<?php

declare(strict_types=1);

namespace App\Http;

use App\Service\WorkoutService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Le rotte degli allenamenti. Ogni metodo fa solo tre cose: legge la richiesta,
 * chiama il service, risponde in JSON. Le regole stanno in WorkoutService.
 */
final class WorkoutController
{
    public function __construct(private WorkoutService $service)
    {
    }

    /** POST /workouts */
    public function start(Request $request, Response $response): Response
    {
        return JsonResponse::with($response, $this->service->start($request->getAttribute('user_id')));
    }

    /** GET /workouts/current */
    public function current(Request $request, Response $response): Response
    {
        return JsonResponse::with($response, $this->service->current($request->getAttribute('user_id')));
    }

    /** PUT /workouts/{id}/exercises/{exerciseId}/sets — $args contiene i pezzi variabili dell'indirizzo */
    public function saveSets(Request $request, Response $response, array $args): Response
    {
        $body = (array) $request->getParsedBody();

        $summary = $this->service->saveSets(
            $request->getAttribute('user_id'),
            (int) $args['id'],
            (int) $args['exerciseId'],
            $body['sets'] ?? null,
        );

        return JsonResponse::with($response, $summary);
    }

    /** POST /workouts/{id}/finish */
    public function finish(Request $request, Response $response, array $args): Response
    {
        return JsonResponse::with($response, $this->service->finish($request->getAttribute('user_id'), (int) $args['id']));
    }

    /** DELETE /workouts/{id} → 204: annulla l'allenamento in corso, serie comprese */
    public function cancel(Request $request, Response $response, array $args): Response
    {
        $this->service->cancel($request->getAttribute('user_id'), (int) $args['id']);

        return $response->withStatus(204);
    }
}
