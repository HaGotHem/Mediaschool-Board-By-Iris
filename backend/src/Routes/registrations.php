<?php
declare(strict_types=1);
use Board\Http\Json;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

// Routes publiques d'inscription. Chargé par src/app.php.
return static function (App $app, Closure $db): void {
    // À réaliser (API-01/API-02) : validation serveur (Registration\Validator), unicité, insertion
    // (Registration\Repository), confirmation et limitation applicative.
    // Un 501 volontaire ne doit jamais être remplacé par une fausse confirmation.
    // Ordre fermé : 503 → 415 → 400 → 422 → INSERT → 201 (409 sur doublon).
    $app->post('/api/registrations', function (): Response {
        if (!filter_var(getenv('REGISTRATIONS_OPEN') ?: 'false', FILTER_VALIDATE_BOOLEAN)) {
            return Json::error('REGISTRATIONS_CLOSED', 'Les inscriptions ne sont pas ouvertes.', 503);
        }
        return Json::error('NOT_IMPLEMENTED', 'L’enregistrement doit être réalisé par le pôle API.', 501);
    });
};
