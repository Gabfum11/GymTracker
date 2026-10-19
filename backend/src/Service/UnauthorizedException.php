<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/** Credenziali o token non validi (401). */
final class UnauthorizedException extends RuntimeException
{
}
