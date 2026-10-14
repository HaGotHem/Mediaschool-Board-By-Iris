<?php
declare(strict_types=1);
use Board\Http\Json;
use Board\Registration\{DuplicateRegistrationException, Repository, Validator};
use Psr\Http\Message\{ServerRequestInterface as Request, ResponseInterface as Response};
use Slim\App;

// Routes publiques d'inscription. Chargé par src/app.php.
// Ordre fermé : 503 → 415 → 400 → 422 → INSERT → 201 (409 sur doublon).
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
        return Json::send(['data' => [
            // Pas de colonne UUID dans 003 : référence dérivée de l'identifiant, sans accès à une fiche publique.
            'reference' => sprintf('INS-%06d', $registration['id']),
            'message' => 'Votre visite est enregistrée.',
            // Date et heure choisies dans le formulaire ; null si le rendez-vous n'a pas été demandé.
            'appointment' => $values['preferred_date'] === null ? null
                : ['date' => $values['preferred_date'], 'time_slot' => $values['preferred_time']],
        ]], 201);
    });

    // ROUTE DE TEST : toutes les inscriptions en JSON. Déclarée uniquement en développement
    // (check-config impose APP_ENV=production en prod) — à supprimer avant livraison.
    if ((getenv('APP_ENV') ?: '') === 'development') {
        $app->get('/api/debug/registrations', function () use ($db): Response {
            $rows = $db()->query(
                "SELECT
                     -- Inscription
                     r.id,
                     r.event_id,
                     r.created_at,
                     -- Visiteur
                     v.id         AS visitor_id,
                     v.last_name,
                     v.first_name,
                     v.birth_date,
                     v.phone,
                     v.email,
                     -- Référentiels
                     cc.label     AS current_class,
                     s.label      AS school,
                     l.label      AS entry_level,
                     sp.label     AS specialty,
                     r.remark,
                     -- Rendez-vous souhaité (formulaire)
                     r.preferred_date,
                     to_char(r.preferred_time, 'HH24:MI') AS preferred_time,
                     -- Rendez-vous attribué
                     r.appointment_date,
                     ts.label     AS time_slot,
                     a.label      AS advisor
                 FROM registrations r
                 JOIN      visitors        v  ON v.id  = r.visitor_id
                 JOIN      schools         s  ON s.id  = r.school_id
                 JOIN      entry_levels    l  ON l.id  = r.entry_level_id
                 LEFT JOIN current_classes cc ON cc.id = r.current_class_id
                 LEFT JOIN specialties     sp ON sp.id = r.specialty_id
                 LEFT JOIN time_slots      ts ON ts.id = r.time_slot_id
                 LEFT JOIN advisors        a  ON a.id  = r.advisor_id
                 ORDER BY r.id"
            )->fetchAll(PDO::FETCH_ASSOC);
            $paris = new DateTimeZone('Europe/Paris');
            $items = array_map(static fn(array $r): array => [
                'id' => (int)$r['id'],
                'reference' => sprintf('INS-%06d', $r['id']),
                'event_id' => (int)$r['event_id'],
                'registered_at' => (new DateTimeImmutable($r['created_at']))->setTimezone($paris)->format(DATE_ATOM),
                'visitor' => [
                    'id' => (int)$r['visitor_id'],
                    'last_name' => $r['last_name'],
                    'first_name' => $r['first_name'],
                    'birth_date' => $r['birth_date'],
                    'phone' => $r['phone'],
                    'email' => $r['email'],
                ],
                'current_class' => $r['current_class'],
                'school' => $r['school'],
                'entry_level' => $r['entry_level'],
                'specialty' => $r['specialty'],
                'remark' => $r['remark'],
                'preferred_appointment' => $r['preferred_date'] === null ? null
                    : ['date' => $r['preferred_date'], 'time' => $r['preferred_time']],
                'assigned_appointment' => $r['time_slot'] === null ? null
                    : ['date' => $r['appointment_date'], 'time_slot' => $r['time_slot'], 'advisor' => $r['advisor']],
            ], $rows);
            // Indenté et sans échappement des / pour une lecture directe dans le navigateur
            return Json::send(['total' => count($items), 'data' => $items], 200, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        });
    }
};
