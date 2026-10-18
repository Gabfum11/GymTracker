<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * "Un'AI che risponde in JSON". È un'interfaccia: dice COSA si può chiedere, non COME.
 * GroqClient la implementa davvero; nei test FakeLlmClient la finge.
 * Cambiare fornitore di AI = scrivere un'altra classe che implementa questa.
 */
interface LlmClient
{
    /**
     * @return string il JSON restituito dal modello (non ancora controllato)
     * @throws LlmException se il servizio non risponde, risponde con un errore o va in timeout
     */
    public function completeJson(string $system, string $user): string;
}
