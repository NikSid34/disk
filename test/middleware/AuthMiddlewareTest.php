<?php

declare(strict_types=1);

namespace app\test\middleware;

use app\middleware\AuthMiddleware;
use app\test\support\BaseTestCase;
use Phake;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddlewareTest extends BaseTestCase {
    public function testReturnsRedirectForUnauthorizedUser(): void {
        // region Arrange.
        $middleware = new AuthMiddleware();
        $handler = Phake::mock(RequestHandlerInterface::class);
        $request = $this->createRequest();
        // endregion.

        // region Act.
        $response = $middleware($request, $handler);
        // endregion.

        // region Assert.
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(['/user/login'], $response->getHeader('Location'));
        Phake::verify($handler, Phake::never())->handle(Phake::anyParameters());
        // endregion.
    }

    public function testDelegatesToHandlerForAuthorizedUser(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 5;
        $middleware = new AuthMiddleware();
        $request = $this->createRequest();
        $expectedResponse = new Response(204);
        $handler = Phake::mock(RequestHandlerInterface::class);
        Phake::when($handler)->handle($request)->thenReturn($expectedResponse);
        // endregion.

        // region Act.
        $response = $middleware($request, $handler);
        // endregion.

        // region Assert.
        $this->assertSame($expectedResponse, $response);
        Phake::verify($handler)->handle($request);
        // endregion.
    }
}
