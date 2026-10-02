<?php
declare(strict_types=1);
namespace Board\Http;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;
final class Json
{
    public static function send(array $payload, int $status = 200): ResponseInterface
    {
        $response = new Response($status);
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withHeader('Cache-Control', 'no-store');
    }
    public static function error(string $code, string $message, int $status, array $fields = []): ResponseInterface
    {
        return self::send(['error' => ['code' => $code, 'message' => $message, 'fields' => (object)$fields]], $status);
    }
}
