<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/** La richiesta è valida ma lo stato attuale non la permette, es. modificare un allenamento già chiuso (409). */
final class ConflictException extends RuntimeException
{
}
