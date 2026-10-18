<?php

declare(strict_types=1);

namespace App\Http;

use App\Repository\ExerciseRepository;
use App\Service\SetValidator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** GET /exercises e POST /exercises */
final class ExerciseController
{
    public function __construct(private ExerciseRepository $exercises)
    {
    }

    public function list(Request $request, Response $response): Response
    {
        $list = $this->exercises->listForUser($request->getAttribute('user_id'));

        return JsonResponse::with($response, array_map(self::format(...), $list));
    }

    /** Crea l'esercizio, o restituisce quello esistente con lo stesso nome (201 = creato, 200 = esisteva già). */
    public function create(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $name = SetValidator::exerciseName($body['name'] ?? '');

        $result = $this->exercises->findOrCreate(
            $request->getAttribute('user_id'),
            $name,
            (bool) ($body['is_weighted_bodyweight'] ?? false),
        );

        return JsonResponse::with($response, self::format($result['exercise']), $result['created'] ? 201 : 200);
    }

    private static function format(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'is_weighted_bodyweight' => (bool) $row['is_weighted_bodyweight'],
        ];
    }
}
