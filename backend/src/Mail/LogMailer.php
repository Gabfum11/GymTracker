<?php

declare(strict_types=1);

namespace App\Mail;

/** Per lo sviluppo: invece di mandare l'email la scrive nel terminale del server (lì trovi il codice). */
final class LogMailer implements Mailer
{
    public function send(string $to, string $subject, string $text): void
    {
        error_log("EMAIL a $to — $subject\n$text");
    }
}
