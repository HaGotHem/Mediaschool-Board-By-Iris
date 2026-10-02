<?php
declare(strict_types=1);
namespace Board\Security;
use Board\Http\Json;
use Psr\Http\Message\{ServerRequestInterface, ResponseInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
final class CsrfMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)
            && !Session::csrfValid($request->getHeaderLine('X-CSRF-Token'))) {
            return Json::error('CSRF_INVALID', 'Rechargez la page avant de réessayer.', 403);
        }
        return $handler->handle($request);
    }
}
