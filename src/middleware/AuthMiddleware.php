<?php


namespace app\middleware;


use app\service\UserService;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;


class AuthMiddleware {
    function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        if (!UserService::isAuthorized()) {
            return (new Response())
                    ->withHeader('Location', '/user/login')
                    ->withStatus(302);
        }

        return $handler->handle($request);
    }
}
