<?php

declare(strict_types=1);

namespace App\Mail;

/** "Qualcosa che sa mandare un'email". Come LlmClient: un'interfaccia, più implementazioni. */
interface Mailer
{
    /** @throws MailException se l'email non è partita */
    public function send(string $to, string $subject, string $text): void;
}
