<?php

declare(strict_types=1);

namespace App\Ai;

use RuntimeException;

/** Il servizio AI non ha risposto (rete, timeout, limite superato, errore del fornitore). Diventa 503. */
final class LlmException extends RuntimeException
{
}
