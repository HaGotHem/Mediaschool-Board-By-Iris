<?php
declare(strict_types=1);
namespace Board\Security;
use Medoo\Medoo;
final class LoginLimiter
{
    public static function allowed(Medoo $db, string $ip): bool
    {
        $key = hash_hmac('sha256', $ip, getenv('APP_SECRET') ?: '');
        // UPSERT atomique : les requêtes simultanées ne contournent pas le compteur.
        $sql = "INSERT INTO login_rate_limits (fingerprint, window_start, attempts)
            VALUES (:key, CURRENT_TIMESTAMP, 1)
            ON CONFLICT (fingerprint) DO UPDATE SET
            attempts = CASE WHEN login_rate_limits.window_start < CURRENT_TIMESTAMP - INTERVAL '15 minutes'
                THEN 1 ELSE login_rate_limits.attempts + 1 END,
            window_start = CASE WHEN login_rate_limits.window_start < CURRENT_TIMESTAMP - INTERVAL '15 minutes'
                THEN CURRENT_TIMESTAMP ELSE login_rate_limits.window_start END
            RETURNING attempts";
        $row = $db->query($sql, [':key' => $key])->fetch();
        return (int)$row['attempts'] <= 20;
    }
}
