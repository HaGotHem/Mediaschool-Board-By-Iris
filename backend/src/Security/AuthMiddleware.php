<?php
declare(strict_types=1);
namespace Board\Security;
use Board\Http\Json;
use Psr\Http\Message\{ServerRequestInterface, ResponseInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
final class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!isset($_SESSION['admin'])) { return Json::error('UNAUTHENTICATED', 'Connexion requise.', 401); }
        return $handler->handle($request->withAttribute('admin', $_SESSION['admin']));
    }
}
