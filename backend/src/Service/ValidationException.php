<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/** Dato non valido. Il messaggio è in italiano e si può mostrare all'utente così com'è. */
final class ValidationException extends RuntimeException
{
}
