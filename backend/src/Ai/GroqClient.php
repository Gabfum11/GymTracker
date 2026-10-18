<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * Chiama Groq (API compatibile con OpenAI) con curl, la libreria HTTP inclusa in PHP.
 * La chiave resta sul server: il telefono non la vede mai.
 */
final class GroqClient implements LlmClient
{
    private const URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(private string $apiKey, private string $model)
    {
    }

    public function completeJson(string $system, string $user): string
    {
        $curl = curl_init(self::URL);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true, // restituisci la risposta invece di stamparla
            CURLOPT_TIMEOUT => 15,          // oltre 15 secondi lasciamo perdere: l'utente è in palestra
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $this->model,
                'temperature' => 0,                              // risposte stabili, non "creative"
                'response_format' => ['type' => 'json_object'],  // il modello deve rispondere con un JSON
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ]),
        ]);

        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new LlmException("Groq non raggiungibile: $error");
        }
        if ($status >= 400) {
            throw new LlmException("Groq ha risposto $status: $body");
        }

        $content = json_decode($body, true)['choices'][0]['message']['content'] ?? null;
        if (!is_string($content)) {
            throw new LlmException('Risposta di Groq senza contenuto');
        }

        return $content;
    }
}
