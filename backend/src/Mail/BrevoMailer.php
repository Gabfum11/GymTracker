<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Manda le email con l'API HTTP di Brevo, come TrackIt:
 * Render (piano gratuito) blocca le porte SMTP in uscita, una chiamata HTTP invece passa.
 */
final class BrevoMailer implements Mailer
{
    private const URL = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(private string $apiKey, private string $from)
    {
    }

    public function send(string $to, string $subject, string $text): void
    {
        $curl = curl_init(self::URL);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['api-key: ' . $this->apiKey, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode([
                'sender' => ['email' => $this->from, 'name' => 'GymTracker'],
                'to' => [['email' => $to]],
                'subject' => $subject,
                'textContent' => $text,
            ]),
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $status >= 400) {
            throw new MailException("Brevo ha risposto $status: $body");
        }
    }
}
