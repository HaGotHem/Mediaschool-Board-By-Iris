<?php
declare(strict_types=1);
namespace Board\Export;
use PHPMailer\PHPMailer\PHPMailer;
final class SummaryMailer
{
    public static function assertRecipient(string $recipient): void
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) { throw new \InvalidArgumentException('Adresse invalide.'); }
        $allowed = array_filter(array_map(fn($x) => strtolower(trim($x)), explode(',', getenv('SUMMARY_RECIPIENT_ALLOWLIST') ?: '')));
        if (!in_array(strtolower(trim($recipient)), $allowed, true)) { throw new \DomainException('Destinataire non autorisé.'); }
    }
    // Exemple à appeler uniquement dans une route authentifiée avec CSRF, validation et limite d'envoi.
    // Aucun envoi n'est effectué par les tests ou le script de démonstration.
    public static function send(string $recipient, string $format, string $content): void
    {
        self::assertRecipient($recipient);
        if (!in_array($format, ['csv', 'pdf'], true)) { throw new \InvalidArgumentException('Format invalide.'); }
        foreach (['SMTP_HOST', 'SMTP_FROM', 'SMTP_USER', 'SMTP_PASSWORD'] as $key) {
            if (!getenv($key)) { throw new \RuntimeException('Service de messagerie non configuré.'); }
        }
        $encryption = getenv('SMTP_ENCRYPTION') ?: 'tls';
        if (!in_array($encryption, ['tls', 'smtps'], true)) { throw new \RuntimeException('Chiffrement SMTP invalide.'); }
        $mail = new PHPMailer(true); $mail->isSMTP();
        $mail->Host = getenv('SMTP_HOST'); $mail->Port = (int)(getenv('SMTP_PORT') ?: 587);
        $mail->SMTPAuth = true; $mail->Username = getenv('SMTP_USER'); $mail->Password = getenv('SMTP_PASSWORD');
        $mail->SMTPSecure = $encryption === 'smtps' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPDebug = 0; $mail->Timeout = 10; $mail->CharSet = 'UTF-8';
        $mail->setFrom(getenv('SMTP_FROM'), 'Mediaschool Board by Iris Nice');
        $mail->addAddress($recipient);
        $mail->Subject = 'Récapitulatif du salon — Mediaschool Board';
        $mail->Body = 'Vous trouverez en pièce jointe le récapitulatif agrégé demandé. Aucun contact nominatif n’est inclus.';
        $mime = $format === 'pdf' ? 'application/pdf' : 'text/csv';
        $mail->addStringAttachment($content, 'recap-salon.' . $format, PHPMailer::ENCODING_BASE64, $mime);
        $mail->send();
    }
}
