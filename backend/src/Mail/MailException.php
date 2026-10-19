<?php

declare(strict_types=1);

namespace App\Mail;

use RuntimeException;

/** L'email non è partita (Brevo non raggiungibile o errore). Diventa 503. */
final class MailException extends RuntimeException
{
}
