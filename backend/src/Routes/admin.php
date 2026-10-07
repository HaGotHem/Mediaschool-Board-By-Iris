<?php
declare(strict_types=1);
use Board\Export\SummaryExport;
use Board\Http\Json;
use Board\Registration\{Filters, Repository};
use Board\Security\{AuthMiddleware, CsrfMiddleware};
use Psr\Http\Message\{ServerRequestInterface as Request, ResponseInterface as Response};
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

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

        $group->get('/registrations/{id:[0-9]+}', $todo);      // API-03 : détail

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
                    JOIN time_slots ts ON ts.id = r.time_slot_id
                    JOIN advisors a ON a.id = r.advisor_id
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

        $group->post('/summary/email', $todo);                 // EXP-03
    })->add(new CsrfMiddleware())->add(new AuthMiddleware());
};