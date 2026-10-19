<?php

declare(strict_types=1);

namespace App\Http;

use App\Repository\ExerciseRepository;
use App\Service\ParserService;
use App\Service\RateLimiter;
use App\Service\SetValidator;
use App\Service\ValidationException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** POST /parse {text, current_exercise?, pending_sets?} → cosa ha capito il bot. Non salva niente. */
final class ParseController
{
    private const MAX_TEXT = 500;

    public function __construct(
        private ParserService $parser,
        private ExerciseRepository $exercises,
        private RateLimiter $limiter,
        private int $dailyLimit,
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $body = (array) $request->getParsedBody();
        $text = trim((string) ($body['text'] ?? ''));
        if ($text === '' || mb_strlen($text) > self::MAX_TEXT) {
            throw new ValidationException('Scrivi un messaggio (al massimo ' . self::MAX_TEXT . ' caratteri)');
        }

        // ogni messaggio costa una chiamata a Groq: limite al giorno per utente (le credenziali demo sono pubbliche)
        $this->limiter->check('parse:' . $request->getAttribute('user_id'), $this->dailyLimit, 86400, 'Hai raggiunto il limite di messaggi per oggi');

        $current = isset($body['current_exercise']) ? (string) $body['current_exercise'] : null;
        $userExercises = array_map(fn ($e) => [
            'id' => (int) $e['id'],
            'name' => $e['name'],
            'is_weighted_bodyweight' => (bool) $e['is_weighted_bodyweight'],
        ], $this->exercises->listForUser($request->getAttribute('user_id')));

        $result = $this->parser->parse($text, $userExercises, $current, $this->pendingSets($body['pending_sets'] ?? null));

        return JsonResponse::with($response, $result);
    }

    /** Le serie della scheda in corso arrivano dal telefono: le controlliamo prima di metterle nel prompt. */
    private function pendingSets(mixed $raw): ?array
    {
        if (!is_array($raw) || $raw === []) {
            return null;
        }
        try {
            return SetValidator::sets($raw);
        } catch (ValidationException) {
            return null;
        }
    }
}
