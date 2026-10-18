<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

/** La risorsa non esiste, o non è dell'utente che la chiede: per lui è come se non esistesse (404). */
final class NotFoundException extends RuntimeException
{
}
