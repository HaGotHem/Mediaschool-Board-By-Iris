<?php
declare(strict_types=1);
use Board\Http\Json;
use Board\Registration\{DuplicateRegistrationException, Repository, Validator};
use Psr\Http\Message\{ServerRequestInterface as Request, ResponseInterface as Response};
use Slim\App;

// Routes publiques d'inscription. Chargé par src/app.php.
// Ordre fermé : 503 → 415 → 400 → 422 → INSERT → 201 (409 sur doublon ou plus de créneau).
return static function (App $app, Closure $db): void {
    $app->post('/api/registrations', function (Request $request) use ($db): Response {
        if (!filter_var(getenv('REGISTRATIONS_OPEN') ?: 'false', FILTER_VALIDATE_BOOLEAN)) {
            return Json::error('REGISTRATIONS_CLOSED', 'Les inscriptions ne sont pas ouvertes.', 503);
        }
        if (!str_starts_with(strtolower($request->getHeaderLine('Content-Type')), 'application/json')) {
            return Json::error('UNSUPPORTED_MEDIA_TYPE', 'Le format JSON est attendu.', 415);
        }
        $body = $request->getParsedBody();
        // JSON malformé, scalaire ou liste → 400 ; {} reste un tableau vide → 422 avec les champs requis.
        if (!is_array($body) || ($body !== [] && array_is_list($body))) {
            return Json::error('INVALID_JSON', 'Corps de requête invalide.', 400);
        }

        $repo = new Repository($db(), (int)(getenv('EVENT_ID') ?: 1));
        $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Paris'));
        ['values' => $values, 'errors' => $errors] = Validator::validate($body, $repo->referenceIds(), $today);
        if ($errors !== []) {
            error_log('board registration 422 fields=' . implode(',', array_keys($errors))); // noms seulement, jamais les valeurs
            return Json::error('VALIDATION_FAILED', 'Corrigez les champs indiqués.', 422, $errors);
        }

        try {
            $registration = $repo->create($values);
        } catch (DuplicateRegistrationException) {
            return Json::error('DUPLICATE_REGISTRATION', 'Une fiche identique a déjà été enregistrée pour ce salon. L’équipe peut vérifier votre inscription.', 409);
        } catch (PDOException $e) {
            if (($e->errorInfo[0] ?? '') === '23503') { // clé étrangère : référentiel supprimé entre-temps
                return Json::error('VALIDATION_FAILED', 'Corrigez les champs indiqués.', 422);
            }
            throw $e; // → 500 neutre via le gestionnaire d'erreurs, sans saisie dans les logs
        }
        if ($registration === null) {
            return Json::error('NO_SLOT_AVAILABLE', 'Tous les créneaux de rendez-vous sont complets.', 409);
        }

        return Json::send(['data' => [
            // Pas de colonne UUID dans 003 : référence dérivée de l'identifiant, sans accès à une fiche publique.
            'reference' => sprintf('INS-%06d', $registration['id']),
            'message' => 'Votre visite est enregistrée.',
            'appointment' => ['date' => $registration['appointment_date'], 'time_slot' => $registration['time_slot']],
        ]], 201);
    });

    // ROUTE DE TEST : liste brute des inscrits. Déclarée uniquement en développement
    // (check-config impose APP_ENV=production en prod) — à supprimer avant livraison.
    if ((getenv('APP_ENV') ?: '') === 'development') {
        $app->get('/api/debug/registrations', function () use ($db): Response {
            $rows = $db()->query(
                "SELECT r.id, to_char(r.created_at AT TIME ZONE 'Europe/Paris', 'YYYY-MM-DD HH24:MI') AS created_at,
                        v.last_name, v.first_name, v.email, s.label AS school, l.label AS entry_level, ts.label AS time_slot
                 FROM registrations r
                 JOIN visitors v ON v.id = r.visitor_id
                 JOIN schools s ON s.id = r.school_id
                 JOIN entry_levels l ON l.id = r.entry_level_id
                 JOIN time_slots ts ON ts.id = r.time_slot_id
                 ORDER BY r.id"
            )->fetchAll(PDO::FETCH_ASSOC);
            $text = count($rows) . " inscription(s)\n\n";
            foreach ($rows as $r) {
                $text .= "#{$r['id']}  {$r['created_at']}  {$r['last_name']} {$r['first_name']} <{$r['email']}>  {$r['school']} / {$r['entry_level']}  RDV {$r['time_slot']}\n";
            }
            $response = new \Slim\Psr7\Response(200);
            $response->getBody()->write($text);
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8')
                ->withHeader('Cache-Control', 'no-store')->withHeader('X-Content-Type-Options', 'nosniff');
        });
    }
};
