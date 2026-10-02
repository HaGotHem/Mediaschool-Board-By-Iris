<?php
declare(strict_types=1);
use Board\Http\Json;
use Board\Infrastructure\Database;
use Board\Security\{Session, AuthMiddleware, CsrfMiddleware, LoginLimiter};
use Psr\Http\Message\{ServerRequestInterface as Request, ResponseInterface as Response};
use Slim\Factory\AppFactory;
use Slim\Exception\HttpException;

// Le connecteur est paresseux : une base indisponible donne une erreur HTTP propre.
$db = static function () { static $connection; return $connection ??= Database::connect(); };
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(function (Request $request, $handler): Response {
    Session::start();
    return $handler->handle($request);
});
$error = $app->addErrorMiddleware(false, false, false);
$error->setDefaultErrorHandler(function (Request $request, Throwable $exception): Response {
    $status = $exception instanceof HttpException ? $exception->getCode() : 500;
    // Ne jamais journaliser le corps de la requête, les secrets ou une requête SQL contenant des données.
    error_log('board error type=' . get_class($exception) . ' status=' . $status);
    return Json::error('HTTP_' . $status, $status === 404 ? 'Route inconnue.' : 'La demande ne peut pas être traitée.', $status);
});
$app->get('/api/health', function () use ($db): Response {
    try { $db()->query('SELECT 1'); }
    catch (Throwable) { return Json::error('DATABASE_UNAVAILABLE', 'Service indisponible.', 503); }
    return Json::send(['data' => ['status' => 'ok', 'starter' => true]]);
});
$app->get('/api/references', function () use ($db): Response {
    $result = [];
    // Liste fermée : aucun nom de table fourni par le client.
    foreach (['schools', 'current_classes', 'entry_levels', 'specialties'] as $table) {
        $result[$table] = $db()->select($table, ['id [Int]', 'code', 'label'], ['ORDER' => ['id' => 'ASC']]);
    }
    $event = $db()->get('events', ['id [Int]', 'label', 'event_date'], ['id' => (int)(getenv('EVENT_ID') ?: 1)]);
    return Json::send(['data' => [...$result, 'event' => $event]]);
});
$app->get('/api/auth/session', function (): Response {
    return Json::send(['data' => ['authenticated' => isset($_SESSION['admin']),
        'user' => $_SESSION['admin'] ?? null, 'csrf_token' => $_SESSION['csrf_token']]]);
});
$app->post('/api/auth/login', function (Request $request) use ($db): Response {
    $body = $request->getParsedBody();
    if (!is_array($body) || !is_string($body['username'] ?? null) || !is_string($body['password'] ?? null)
        || strlen($body['username']) > 100 || strlen($body['password']) > 200) {
        return Json::error('INVALID_INPUT', 'Identifiants invalides.', 422);
    }
    $ip = (string)($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
    if (!LoginLimiter::allowed($db(), $ip)) {
        return Json::error('RATE_LIMITED', 'Trop de tentatives. Réessayez dans quinze minutes.', 429)->withHeader('Retry-After', '900');
    }
    $user = $db()->get('admins', ['id [Int]', 'username', 'password_hash'], ['username' => trim($body['username']), 'active' => true]);
    // Hash fixe de temporisation pour limiter la différence entre compte connu et inconnu.
    $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
    $valid = password_verify($body['password'], $user['password_hash'] ?? $dummy);
    if (!$user || !$valid) { return Json::error('INVALID_CREDENTIALS', 'Identifiants incorrects.', 401); }
    session_regenerate_id(true);
    $_SESSION['admin'] = ['id' => $user['id'], 'username' => $user['username']];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return Json::send(['data' => ['user' => $_SESSION['admin'], 'csrf_token' => $_SESSION['csrf_token']]]);
})->add(new CsrfMiddleware());
$app->post('/api/auth/logout', function (): Response {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return Json::send(['data' => ['authenticated' => false, 'csrf_token' => $_SESSION['csrf_token']]]);
})->add(new CsrfMiddleware())->add(new AuthMiddleware());

// Routes métier : plus aucune route ne s'ajoute dans ce fichier.
(require __DIR__ . '/Routes/registrations.php')($app, $db);
(require __DIR__ . '/Routes/admin.php')($app, $db);
return $app;
