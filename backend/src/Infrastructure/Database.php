<?php
declare(strict_types=1);
namespace Board\Infrastructure;
use Medoo\Medoo;
use PDO;
final class Database
{
    public static function connect(): Medoo
    {
        return new Medoo([
            'type' => 'pgsql', 'host' => getenv('DB_HOST') ?: 'db',
            'port' => (int)(getenv('DB_PORT') ?: 5432),
            'database' => getenv('DB_NAME') ?: 'board',
            'username' => getenv('DB_USER') ?: 'board', 'password' => getenv('DB_PASSWORD') ?: '',
            'error' => PDO::ERRMODE_EXCEPTION,
            'option' => [PDO::ATTR_EMULATE_PREPARES => false],
        ]);
    }
}
