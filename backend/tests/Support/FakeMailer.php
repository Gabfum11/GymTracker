<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Mail\Mailer;

/** Email finte per i test: non parte niente, ma si ricordano tutte le email "inviate". */
final class FakeMailer implements Mailer
{
    /** @var list<array{to: string, subject: string, text: string}> */
    public array $sent = [];

    public function send(string $to, string $subject, string $text): void
    {
        $this->sent[] = ['to' => $to, 'subject' => $subject, 'text' => $text];
    }

    /** Il codice a 6 cifre dell'ultima email. */
    public function lastCode(): string
    {
        preg_match('/\b(\d{6})\b/', end($this->sent)['text'] ?? '', $m);

        return $m[1] ?? '';
    }
}
