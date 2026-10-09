<?php
declare(strict_types=1);
use Board\Export\SummaryExport;
use Board\Http\Json;
use Board\Registration\{Filters, Repository, Validator};
use Board\Security\{AuthMiddleware, CsrfMiddleware};
use Psr\Http\Message\{ServerRequestInterface as Request, ResponseInterface as Response};
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Board\Export\SummaryMailer;

// Routes privées du back-office : Auth puis CSRF, jamais de route admin en dehors de ce groupe.
// Chargé par src/app.php.
return static function (App $app, Closure $db): void {
    $app->group('/api/admin', function (RouteCollectorProxy $group) use ($db): void {
        $todo = fn(): Response => Json::error('NOT_IMPLEMENTED', 'Fonctionnalité à réaliser. Consultez le contrat API.', 501);

        // API-03 : liste paginée, filtrable par école / niveau, limitée au salon actif.
        $group->get('/registrations', function (Request $request) use ($db): Response {
            ['filters' => $filters, 'errors' => $errors] = Filters::fromQuery($request->getQueryParams());
            if ($errors !== []) {
                return Json::error('VALIDATION_FAILED', 'Paramètres de liste invalides.', 422, $errors);
            }
            $repo = new Repository($db(), (int)(getenv('EVENT_ID') ?: 1));
            return Json::send(['data' => $repo->list($filters)]);
        });

        // API-04 : synthèse par école / niveau, mêmes filtres que la liste. /stats est un alias de /summary.
        $summary = function (Request $request) use ($db): Response {
            ['filters' => $filters, 'errors' => $errors] = Filters::fromQuery($request->getQueryParams());
            if ($errors !== []) {
                return Json::error('VALIDATION_FAILED', 'Paramètres de synthèse invalides.', 422, $errors);
            }
            $eventId = (int)(getenv('EVENT_ID') ?: 1);
            return Json::send(['data' => [
                'event' => $db()->get('events', ['id [Int]', 'label'], ['id' => $eventId]),
                'filters' => ['school_id' => $filters->schoolId, 'entry_level_id' => $filters->entryLevelId],
                ...(new Repository($db(), $eventId))->summary($filters),
                'generated_at' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))->format(DATE_ATOM),
            ]]);
        };
        $group->get('/summary', $summary);
        $group->get('/stats', $summary);

        // API-03 : détail. Une fiche d'un autre salon répond 404 (on ne confirme pas son existence).
        $group->get('/registrations/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($db): Response {
            if (strlen($args['id']) > 18) { // évite un dépassement d'entier
                return Json::error('NOT_FOUND', 'Fiche introuvable.', 404);
            }
            $row = (new Repository($db(), (int)(getenv('EVENT_ID') ?: 1)))->find((int)$args['id']);
            return $row === null
                ? Json::error('NOT_FOUND', 'Fiche introuvable.', 404)
                : Json::send(['data' => $row]);
        });

        // Modification d'une fiche : mêmes règles que le formulaire public (Validator), CSRF exigé par le groupe.
        $group->patch('/registrations/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($db): Response {
            if (strlen($args['id']) > 18) {
                return Json::error('NOT_FOUND', 'Fiche introuvable.', 404);
            }
            if (!str_starts_with(strtolower($request->getHeaderLine('Content-Type')), 'application/json')) {
                return Json::error('UNSUPPORTED_MEDIA_TYPE', 'Le format JSON est attendu.', 415);
            }
            $body = $request->getParsedBody();
            if (!is_array($body) || ($body !== [] && array_is_list($body))) {
                return Json::error('INVALID_JSON', 'Corps de requête invalide.', 400);
            }

            $repo = new Repository($db(), (int)(getenv('EVENT_ID') ?: 1));
            $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Paris'));
            ['values' => $values, 'errors' => $errors] = Validator::validate($body, $repo->referenceIds(), $today);
            if ($errors !== []) {
                return Json::error('VALIDATION_FAILED', 'Corrigez les champs indiqués.', 422, $errors);
            }

            try {
                $updated = $repo->update((int)$args['id'], $values);
            } catch (PDOException $e) {
                if (($e->errorInfo[0] ?? '') === '23505') { // e-mail déjà utilisé par un autre visiteur
                    return Json::error('VALIDATION_FAILED', 'Corrigez les champs indiqués.', 422,
                        ['email' => 'Adresse déjà utilisée par un autre visiteur.']);
                }
                if (($e->errorInfo[0] ?? '') === '23503') {
                    return Json::error('VALIDATION_FAILED', 'Corrigez les champs indiqués.', 422);
                }
                throw $e;
            }
            if (!$updated) {
                return Json::error('NOT_FOUND', 'Fiche introuvable.', 404);
            }
            return Json::send(['data' => $repo->find((int)$args['id'])]);
        });

        // Suppression d'une inscription du salon actif (CSRF exigé par le groupe).
        // Le visiteur est supprimé aussi s'il n'a plus aucune inscription : pas de données personnelles orphelines.
        $group->delete('/registrations/{id:[0-9]+}', function (Request $request, Response $response, array $args) use ($db): Response {
            if (strlen($args['id']) > 18) {
                return Json::error('NOT_FOUND', 'Fiche introuvable.', 404);
            }
            $pdo = $db()->pdo;
            $pdo->beginTransaction();
            try {
                $delete = $pdo->prepare(
                    'DELETE FROM registrations WHERE id = :id AND event_id = :event_id RETURNING visitor_id'
                );
                $delete->bindValue(':id', (int)$args['id'], PDO::PARAM_INT);
                $delete->bindValue(':event_id', (int)(getenv('EVENT_ID') ?: 1), PDO::PARAM_INT);
                $delete->execute();
                $visitorId = $delete->fetchColumn();
                if ($visitorId === false) { // absente ou d'un autre salon : 404, on ne confirme pas son existence
                    $pdo->rollBack();
                    return Json::error('NOT_FOUND', 'Fiche introuvable.', 404);
                }

                $orphan = $pdo->prepare(
                    'DELETE FROM visitors v WHERE v.id = :id
                     AND NOT EXISTS (SELECT 1 FROM registrations r WHERE r.visitor_id = v.id)'
                );
                $orphan->bindValue(':id', (int)$visitorId, PDO::PARAM_INT);
                $orphan->execute();
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            return Json::send(['data' => ['id' => (int)$args['id'], 'deleted' => true]]);
        });

        // Export nominatif : toutes les fiches, du premier au dernier inscrit, sans l'id.
        $group->get('/exports/registrations/csv', function (Request $request, Response $response) use ($db): Response {
            ['filters' => $filters, 'errors' => $errors] = Filters::fromQuery($request->getQueryParams());
            if ($errors !== []) {
                return Json::error('VALIDATION_FAILED', 'Paramètres d’export invalides.', 422, $errors);
            }

            $where = ['r.event_id = :event_id'];
            $map = [':event_id' => (int)(getenv('EVENT_ID') ?: 1)];
            if ($filters->schoolId !== null) {
                $where[] = 'r.school_id = :school_id';
                $map[':school_id'] = $filters->schoolId;
            }
            if ($filters->entryLevelId !== null) {
                $where[] = 'r.entry_level_id = :entry_level_id';
                $map[':entry_level_id'] = $filters->entryLevelId;
            }

            $sql = "SELECT to_char(r.created_at AT TIME ZONE 'Europe/Paris', 'DD/MM/YYYY HH24:MI:SS') AS created_at,
                           v.last_name, v.first_name,
                           to_char(v.birth_date, 'DD/MM/YYYY') AS birth_date,
                           v.phone, v.email,
                           cc.label AS current_class, s.label AS school, el.label AS entry_level,
                           sp.label AS specialty, r.remark,
                           to_char(r.appointment_date, 'DD/MM/YYYY') AS appointment_date,
                           ts.label AS time_slot, a.label AS advisor
                    FROM registrations r
                    JOIN visitors v ON v.id = r.visitor_id
                    JOIN schools s ON s.id = r.school_id
                    JOIN entry_levels el ON el.id = r.entry_level_id
                    LEFT JOIN time_slots ts ON ts.id = r.time_slot_id
                    LEFT JOIN advisors a ON a.id = r.advisor_id
                    LEFT JOIN current_classes cc ON cc.id = r.current_class_id
                    LEFT JOIN specialties sp ON sp.id = r.specialty_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY r.created_at ASC, r.id ASC";

            $rows = $db()->query($sql, $map)->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(SummaryExport::registrationsCsv($rows));
            return $response
                ->withHeader('Content-Type', 'text/csv; charset=utf-8')
                ->withHeader('Content-Disposition', 'attachment; filename="inscrits-salon.csv"')
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('X-Content-Type-Options', 'nosniff');
        });

        // EXP-01/02 : export du récapitulatif agrégé (mêmes filtres que /summary)
        $group->get('/exports/{format:csv|pdf}', function (Request $request, Response $response, array $args) use ($db): Response {
            ['filters' => $filters, 'errors' => $errors] = Filters::fromQuery($request->getQueryParams());
            if ($errors !== []) {
                return Json::error('VALIDATION_FAILED', 'Paramètres d’export invalides.', 422, $errors);
            }
            $eventId = (int)(getenv('EVENT_ID') ?: 1);
            $groups  = (new Repository($db(), $eventId))->summary($filters)['groups'];

            if ($args['format'] === 'csv') {
                $content = SummaryExport::csv($groups);
                $type = 'text/csv; charset=utf-8';
            } else {
                $event = $db()->get('events', ['label'], ['id' => $eventId]);
                $content = SummaryExport::pdf($event['label'] ?? 'Salon', $groups);
                $type = 'application/pdf';
            }

            $response->getBody()->write($content);
            return $response
                ->withHeader('Content-Type', $type)
                ->withHeader('Content-Disposition', 'attachment; filename="recap-salon.' . $args['format'] . '"')
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('X-Content-Type-Options', 'nosniff');
        });

        // EXP-03 : envoi du récapitulatif agrégé par e-mail (Brevo)
$group->post('/summary/email', function (Request $request) use ($db): Response {
    if (!str_starts_with(strtolower($request->getHeaderLine('Content-Type')), 'application/json')) {
        return Json::error('UNSUPPORTED_MEDIA_TYPE', 'Le format JSON est attendu.', 415);
    }
    $body = $request->getParsedBody();
    if (!is_array($body) || array_is_list($body)) {
        return Json::error('INVALID_JSON', 'Corps de requête invalide.', 400);
    }

    $recipient = trim((string)($body['recipient'] ?? ''));
    $format    = (string)($body['format'] ?? 'csv');

    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return Json::error('VALIDATION_FAILED', 'Adresse e-mail invalide.', 422, ['recipient' => 'Adresse invalide.']);
    }
    if (!in_array($format, ['csv', 'pdf'], true)) {
        return Json::error('VALIDATION_FAILED', 'Format invalide.', 422, ['format' => 'Format invalide.']);
    }

    $eventId = (int)(getenv('EVENT_ID') ?: 1);
    $groups  = (new Repository($db(), $eventId))->summary(Filters::fromQuery([])['filters'])['groups'];

    if ($format === 'csv') {
        $content = SummaryExport::csv($groups);
    } else {
        $event   = $db()->get('events', ['label'], ['id' => $eventId]);
        $content = SummaryExport::pdf($event['label'] ?? 'Salon', $groups);
    }

    try {
        SummaryMailer::send($recipient, $format, $content);
    } catch (\Throwable $e) {
        error_log('Envoi récapitulatif : ' . $e->getMessage());
        return Json::error('MAIL_FAILED', 'Envoi impossible pour le moment.', 502);
    }

    return Json::send(['data' => ['sent' => true]]);
});                // EXP-03
    })->add(new CsrfMiddleware())->add(new AuthMiddleware());
};