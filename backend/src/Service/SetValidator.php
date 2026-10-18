<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Controlla serie e nomi di esercizi prima di salvarli (spec §5.2).
 * Vale sia per quello che scrive l'utente sia per quello che risponde l'AI.
 */
final class SetValidator
{
    private const MAX_SETS = 20;
    private const MAX_NAME_LENGTH = 60;

    /**
     * @return list<array{weight_kg: float, reps: int}>
     * @throws ValidationException
     */
    public static function sets(mixed $raw): array
    {
        if (!is_array($raw) || !array_is_list($raw) || $raw === []) {
            throw new ValidationException('Serve almeno una serie');
        }
        if (count($raw) > self::MAX_SETS) {
            throw new ValidationException('Al massimo ' . self::MAX_SETS . ' serie per esercizio');
        }

        $clean = [];
        foreach ($raw as $i => $set) {
            $n = $i + 1;
            $kg = self::toNumber($set['weight_kg'] ?? null);
            $reps = self::toNumber($set['reps'] ?? null);

            // fmod($kg * 4, 1) == 0 vuol dire "multiplo di 0,25": 42.5 * 4 = 170 (intero), 42.3 * 4 = 169.2 (no)
            if ($kg === null || $kg < 0 || $kg > 500 || fmod($kg * 4, 1) != 0) {
                throw new ValidationException("Serie $n: i kg devono essere tra 0 e 500, a passi di 0,25");
            }
            if ($reps === null || $reps < 1 || $reps > 100 || floor($reps) != $reps) {
                throw new ValidationException("Serie $n: le ripetizioni devono essere un numero intero tra 1 e 100");
            }

            $clean[] = ['weight_kg' => $kg, 'reps' => (int) $reps];
        }

        return $clean;
    }

    /** "  dip   zavorrati " → "Dip zavorrati" */
    public static function exerciseName(mixed $raw): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $raw));

        if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new ValidationException('Il nome dell\'esercizio deve avere da 1 a ' . self::MAX_NAME_LENGTH . ' caratteri');
        }

        // mb_ perché "è" occupa più byte: ucfirst() la romperebbe
        return mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1);
    }

    /** Accetta 45, 42.5, "42.5" e "42,5". Tutto il resto → null. */
    private static function toNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));

            return is_numeric($value) ? (float) $value : null;
        }

        return null;
    }
}
