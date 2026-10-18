<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Ai\LlmClient;
use App\Ai\LlmException;

/**
 * AI finta per i test: risponde sempre con la stringa che le dai (o lancia l'eccezione che le dai)
 * e si ricorda l'ultimo prompt ricevuto, così il test può controllarlo.
 */
final class FakeLlmClient implements LlmClient
{
    public string $lastSystem = '';
    public string $lastUser = '';

    public function __construct(private string|LlmException $answer)
    {
    }

    public function completeJson(string $system, string $user): string
    {
        $this->lastSystem = $system;
        $this->lastUser = $user;

        if ($this->answer instanceof LlmException) {
            throw $this->answer;
        }

        return $this->answer;
    }
}
