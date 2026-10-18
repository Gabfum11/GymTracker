<?php

declare(strict_types=1);

namespace App\Service;

use App\Ai\LlmClient;

/**
 * Trasforma quello che l'utente scrive ("dip 45 per 8, poi 45x6") in dati ordinati, usando l'AI.
 * La risposta dell'AI NON è fidata: ogni campo passa da SetValidator. Se qualcosa non torna,
 * il bot fa una domanda invece di indovinare. Questo servizio non salva niente.
 */
final class ParserService
{
    private const FALLBACK = 'Non ho capito, puoi riscriverlo? Es. 45 kg × 8, oppure "3 serie da 8 con 45"';

    public function __construct(private LlmClient $llm)
    {
    }

    /**
     * @param list<array{id: int, name: string, is_weighted_bodyweight: bool}> $existingExercises
     * @param list<array{weight_kg: float, reps: int}>|null $pendingSets serie nella scheda non ancora confermata
     * @return array uno dei tre esiti: sets, exercise, question (spec §6.4)
     */
    public function parse(string $text, array $existingExercises, ?string $currentExercise, ?array $pendingSets): array
    {
        $answer = $this->llm->completeJson($this->systemPrompt($existingExercises, $currentExercise, $pendingSets), $text);

        try {
            return $this->check(json_decode($answer, true), $existingExercises, $currentExercise);
        } catch (ValidationException) {
            return ['type' => 'question', 'text' => self::FALLBACK];
        }
    }

    /** Controlla la risposta dell'AI campo per campo; qualunque problema → ValidationException. */
    private function check(mixed $ai, array $existing, ?string $current): array
    {
        $type = is_array($ai) ? ($ai['type'] ?? null) : null;

        if ($type === 'question') {
            $text = trim((string) ($ai['text'] ?? ''));
            if ($text === '' || mb_strlen($text) > 300) {
                throw new ValidationException('domanda non valida');
            }

            return ['type' => 'question', 'text' => $text];
        }
        if ($type !== 'sets' && $type !== 'exercise') {
            throw new ValidationException('tipo sconosciuto');
        }

        // l'esercizio: quello riconosciuto tra gli esistenti, altrimenti quello scritto, altrimenti quello corrente
        $byId = array_column($existing, null, 'id');
        $matchedId = $ai['matched_existing_id'] ?? null;
        $matched = is_int($matchedId) ? ($byId[$matchedId] ?? null) : null;
        if ($matched === null && ($ai['exercise'] ?? null) === null && $current !== null) {
            $matched = $this->findByName($existing, $current);
            $ai['exercise'] = $current;
        }

        $name = SetValidator::exerciseName($matched['name'] ?? $ai['exercise'] ?? '');
        $result = [
            'type' => $type,
            'exercise' => $name,
            'typed_name' => trim((string) ($ai['typed_name'] ?? $name)),
            'matched_existing_id' => $matched ? (int) $matched['id'] : null,
            'weighted' => $matched ? (bool) $matched['is_weighted_bodyweight'] : (bool) ($ai['weighted'] ?? false),
        ];
        if ($type === 'sets') {
            $result['sets'] = SetValidator::sets($ai['sets'] ?? null);
        }

        return $result;
    }

    private function findByName(array $existing, string $name): ?array
    {
        foreach ($existing as $exercise) {
            if (mb_strtolower($exercise['name']) === mb_strtolower($name)) {
                return $exercise;
            }
        }

        return null;
    }

    private function systemPrompt(array $existing, ?string $current, ?array $pending): string
    {
        $list = $existing === []
            ? '(nessuno)'
            : implode("\n", array_map(fn ($e) => "{$e['id']}: {$e['name']}", $existing));
        $pendingText = $pending
            ? implode("\n", array_map(fn ($s, $i) => ($i + 1) . ") {$s['weight_kg']} kg × {$s['reps']}", $pending, array_keys($pending)))
            : '(nessuna)';
        $currentText = $current ?? '(nessuno)';

        return <<<PROMPT
        Sei l'assistente di un'app per registrare gli allenamenti in palestra. L'utente scrive in italiano,
        in modo informale, l'esercizio che sta facendo e/o le serie (peso in kg e ripetizioni).
        Rispondi SOLO con un oggetto JSON, in uno di questi tre formati:

        1) serie: {"type":"sets","exercise":"<nome>","typed_name":"<come l'ha scritto>","matched_existing_id":<id o null>,"weighted":<true/false>,"sets":[{"weight_kg":<numero>,"reps":<intero>}]}
        2) solo esercizio: {"type":"exercise","exercise":"<nome>","typed_name":"<come l'ha scritto>","matched_existing_id":<id o null>,"weighted":<true/false>}
        3) domanda: {"type":"question","text":"<una domanda breve in italiano>"}

        Regole:
        - "exercise": nome pulito con l'iniziale maiuscola (es. "Panca piana"). Se nel messaggio non c'è un esercizio, usa null: vale l'esercizio corrente.
        - "typed_name": solo il nome dell'esercizio come l'ha scritto l'utente (es. "dips", "panca"), senza numeri. Se il messaggio non nomina un esercizio, usa null.
        - "matched_existing_id": se l'esercizio è lo stesso di uno di quelli dell'utente (anche scritto diversamente, al plurale, abbreviato o in un sinonimo, es. "dips"/"parallele" per "Dip"), metti il suo id; altrimenti null. Usa SOLO id di questo elenco.
        - Esercizi a corpo libero senza peso: weight_kg = 0. "+10" o "con 10 kg di zavorra" = 10 e "weighted": true.
        - "3 serie da 8 con 45" = tre serie uguali di 45 kg × 8.
        - Se non è chiaro quale numero sono i kg e quale le ripetizioni, NON indovinare: rispondi con una domanda (es. "8 sono le ripetizioni o i kg?").
        - Se il messaggio corregge le serie della scheda in corso (es. "la seconda erano 7"), rispondi con type "sets" e la lista COMPLETA aggiornata.
        - Se il messaggio non riguarda l'allenamento, rispondi con una domanda che riporti l'utente all'esercizio.

        Esercizi dell'utente (id: nome):
        {$list}

        Esercizio corrente: {$currentText}

        Serie nella scheda in corso, non ancora confermate:
        {$pendingText}
        PROMPT;
    }
}
