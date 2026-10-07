<?php
declare(strict_types=1);

namespace Board\Export;

final class SummaryMailer
{
    public static function assertRecipient(string $recipient): void
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Adresse invalide.');
        }
    }

    public static function send(string $recipient, string $format, string $content): void
    {
        self::assertRecipient($recipient);

        if (!in_array($format, ['csv', 'pdf'], true)) {
            throw new \InvalidArgumentException('Format invalide.');
        }

        $apiKey = getenv('BREVO_API_KEY');
        $from   = getenv('BREVO_SENDER_EMAIL');
        if (!$apiKey || !$from) {
            throw new \RuntimeException('Service de messagerie non configuré.');
        }

        $payload = [
            'sender'      => ['name' => 'Mediaschool Board by Iris Nice', 'email' => $from],
            'to'          => [['email' => $recipient]],
            'subject'     => 'Récapitulatif du salon — Mediaschool Board',
            'htmlContent' => '<p>Bonjour,</p><p>Vous trouverez en pièce jointe le récapitulatif du salon.</p>',
            'attachment'  => [[
                'name'    => 'recap-salon.' . $format,
                'content' => base64_encode($content),
            ]],
        ];

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'content-type: application/json',
                'api-key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
        ]);
        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 201) {
            error_log('Brevo error ' . $status . ' : ' . (string) $response);
            throw new \RuntimeException('Échec de l\'envoi du mail.');
        }
    }
}