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

        $apiKey    = getenv('MAILJET_API_KEY');
        $secretKey = getenv('MAILJET_SECRET_KEY');
        $from      = getenv('MAILJET_SENDER_EMAIL');
        if (!$apiKey || !$secretKey || !$from) {
            throw new \RuntimeException('Service de messagerie non configuré.');
        }

        $payload = [
            'Messages' => [[
                'From'        => ['Email' => $from, 'Name' => 'Mediaschool Board by Iris Nice'],
                'To'          => [['Email' => $recipient]],
                'Subject'     => 'Récapitulatif du salon — Mediaschool Board',
                'HTMLPart'    => '<p>Bonjour,</p><p>Vous trouverez en pièce jointe le récapitulatif du salon.</p>',
                'Attachments' => [[
                    'ContentType'   => $format === 'pdf' ? 'application/pdf' : 'text/csv',
                    'Filename'      => 'recap-salon.' . $format,
                    'Base64Content' => base64_encode($content),
                ]],
            ]],
        ];

        $ch = curl_init('https://api.mailjet.com/v3.1/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_USERPWD        => $apiKey . ':' . $secretKey,
            CURLOPT_HTTPHEADER     => [
                'accept: application/json',
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
        ]);
        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            error_log('Mailjet error ' . $status . ' : ' . (string) $response);
            throw new \RuntimeException('Échec de l\'envoi du mail.');
        }
    }
}