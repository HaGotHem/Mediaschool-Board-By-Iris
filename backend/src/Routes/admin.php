<?php
declare(strict_types=1);
use Board\Http\Json;
use Board\Security\{AuthMiddleware, CsrfMiddleware};
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

// Routes privées du back-office : Auth puis CSRF, jamais de route admin en dehors de ce groupe.
// Chargé par src/app.php.
return static function (App $app, Closure $db): void {
    $app->group('/api/admin', function (RouteCollectorProxy $group): void {
        $todo = fn(): Response => Json::error('NOT_IMPLEMENTED', 'Fonctionnalité à réaliser. Consultez le contrat API.', 501);
        $group->get('/registrations', $todo);                  // API-03 : liste paginée
        $group->get('/registrations/{id:[0-9]+}', $todo);      // API-03 : détail
        $group->get('/summary', $todo);                        // API-04 : synthèse
        $group->get('/exports/{format:csv|pdf}', $todo);       // EXP-01/02
        $group->post('/summary/email', $todo);                 // EXP-03
    })->add(new CsrfMiddleware())->add(new AuthMiddleware());
};
