<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/** Troppi tentativi in poco tempo (429). */
final class TooManyRequestsException extends RuntimeException
{
}
