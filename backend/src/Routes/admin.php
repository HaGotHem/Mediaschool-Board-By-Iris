<?php
declare(strict_types=1);
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
        $group->get('/exports/{format:csv|pdf}', $todo);       // EXP-01/02
        $group->post('/summary/email', $todo);                 // EXP-03
    })->add(new CsrfMiddleware())->add(new AuthMiddleware());
};
