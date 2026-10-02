<?php
declare(strict_types=1);
namespace Board\Security;
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) { return; }
        $path = getenv('SESSION_PATH');
        if ($path) { session_save_path($path); }
        session_name('board_session');
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/',
            // Le proxy termine HTTPS ; on utilise l'URL configurée, jamais un X-Forwarded-Proto libre.
            'secure' => str_starts_with(getenv('APP_URL') ?: '', 'https://'),
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        if (!session_start()) { throw new \RuntimeException('Stockage de session indisponible.'); }
        if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 1800) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    }
    public static function csrfValid(string $token): bool
    {
        return $token !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}
