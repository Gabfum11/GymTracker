<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/** Azione non permessa in questo stato, es. login con email non verificata (403). */
final class ForbiddenException extends RuntimeException
{
}
