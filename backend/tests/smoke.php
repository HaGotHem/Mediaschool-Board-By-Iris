<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Board\Infrastructure\Database;
use Slim\Psr7\Factory\ServerRequestFactory;
use Board\Security\Session;
Session::start();
$app = require dirname(__DIR__) . '/src/app.php';
$connectionFactory = $db;
$db = $connectionFactory(); $checks = 0;
function request(string $method, string $path, array $body = [], string $csrf = ''): array {
    global $app;
    $request = (new ServerRequestFactory())->createServerRequest($method, $path, ['REMOTE_ADDR' => '__starter_smoke__']);
    if ($body) { $request = $request->withParsedBody($body); }
    if ($csrf) { $request = $request->withHeader('X-CSRF-Token', $csrf); }
    $response = $app->handle($request);
    return [$response->getStatusCode(), json_decode((string)$response->getBody(), true)];
}
function expect(bool $condition, string $message): void { global $checks; $checks++; if (!$condition) { throw new RuntimeException($message); } }
$username = '__smoke_' . bin2hex(random_bytes(5)); $password = bin2hex(random_bytes(12));
$key = hash_hmac('sha256', '__starter_smoke__', getenv('APP_SECRET') ?: '');
try {
    unset($_SESSION['admin']);
    expect(request('GET','/api/health')[0] === 200, 'Santé incorrecte');
    [$status, $refs] = request('GET','/api/references');
    expect($status === 200 && count($refs['data']['schools']) === 4, 'Écoles incorrectes');
    expect(count($refs['data']['entry_levels']) === 11 && count($refs['data']['specialties']) === 11, 'Transcription incomplète');
    foreach (['/api/admin/registrations','/api/admin/summary','/api/admin/exports/csv','/api/admin/exports/pdf'] as $path) {
        expect(request('GET',$path)[0] === 401, 'Route privée accessible sans connexion');
    }
    expect(request('POST','/api/auth/login',['username'=>'x','password'=>'y'])[0] === 403, 'Login sans CSRF accepté');
    $db->insert('admins', ['username'=>$username,'password_hash'=>password_hash($password,PASSWORD_DEFAULT)]);
    $token = $_SESSION['csrf_token'];
    expect(request('POST','/api/auth/login',['username'=>$username,'password'=>'incorrect'],$token)[0] === 401, 'Mauvais mot de passe accepté');
    expect(request('POST','/api/auth/login',['username'=>$username,'password'=>$password],$token)[0] === 200, 'Login valide refusé');
    $token = $_SESSION['csrf_token'];
    expect(request('POST','/api/admin/summary/email')[0] === 403, 'Action admin sans CSRF acceptée');
    expect(request('POST','/api/auth/logout',[],$token)[0] === 200, 'Logout refusé');
    expect(request('GET','/api/admin/registrations')[0] === 401, 'Session encore autorisée après logout');
    expect(request('GET','/api/unknown')[0] === 404, 'Route inconnue incorrecte');
} finally {
    $db->delete('admins',['username'=>$username]); $db->delete('login_rate_limits',['fingerprint'=>$key]); unset($_SESSION['admin']);
}
echo "$checks vérifications Slim/PostgreSQL du socle réussies. Les routes métier restent à tester.\n";
