<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$username = trim($argv[1] ?? '');
if (!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/', $username)) { fwrite(STDERR, "Usage : php bin/create-admin.php identifiant (3 à 80 caractères)\n"); exit(1); }
if (!function_exists('shell_exec') || trim((string)shell_exec('stty -g 2>/dev/null')) === '') {
    fwrite(STDERR, "Un terminal interactif est requis. Utilisez docker compose exec api ... sans -T.\n"); exit(1);
}
$state = trim((string)shell_exec('stty -g'));
shell_exec('stty -echo');
try {
    fwrite(STDOUT, 'Mot de passe (12 caractères minimum, saisie masquée) : ');
    $password = rtrim((string)fgets(STDIN), "\r\n"); echo "\n";
    fwrite(STDOUT, 'Confirmation : '); $confirm = rtrim((string)fgets(STDIN), "\r\n"); echo "\n";
} finally { shell_exec('stty ' . escapeshellarg($state)); }
if (strlen($password) < 12 || strlen($password) > 200 || $password !== $confirm) { fwrite(STDERR, "Mot de passe trop court, trop long ou confirmation différente.\n"); exit(1); }
$db = Board\Infrastructure\Database::connect();
$db->query('INSERT INTO admins(username, password_hash) VALUES (:username, :hash) ON CONFLICT(username) DO UPDATE SET password_hash = EXCLUDED.password_hash, active = true',
    [':username' => $username, ':hash' => password_hash($password, PASSWORD_DEFAULT)]);
echo "Compte créé ou mot de passe renouvelé. Aucun mot de passe en clair n’est stocké.\n";
