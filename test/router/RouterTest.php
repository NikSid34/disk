<?php

declare(strict_types=1);

namespace app\test\router;

use app\controller\ApiController;
use app\controller\DiskController;
use app\controller\UserController;
use app\middleware\AuthMiddleware;
use app\router\Router;
use app\test\support\BaseTestCase;
use Phake;
use Slim\Factory\AppFactory;
use Slim\Routing\Route;
use Slim\Routing\RouteCollectorProxy;

class RouterTest extends BaseTestCase {
    public function testDiskRegistersExpectedRoutes(): void {
        // region Arrange.
        $router = new Router(
                Phake::mock(DiskController::class),
                Phake::mock(UserController::class),
                Phake::mock(ApiController::class),
                new AuthMiddleware()
        );
        $app = AppFactory::create();
        $app->group('/disk', function (RouteCollectorProxy $group) use ($router): void {
            $router->disk($group);
        });
        // endregion.

        // region Act.
        $routes = array_values($app->getRouteCollector()->getRoutes());
        // endregion.

        // region Assert.
        $this->assertCount(16, $routes);
        $this->assertSame(['GET'], $routes[0]->getMethods());
        $this->assertSame('/disk/', $routes[0]->getPattern());
        $this->assertSame('files', $routes[0]->getName());
        $this->assertSame('/disk/uploadFile', $routes[1]->getPattern());
        $this->assertSame('uploadFile', $routes[1]->getName());
        $this->assertSame('/disk/folders', $routes[15]->getPattern());
        $this->assertSame('folders', $routes[15]->getName());
        // endregion.
    }

    public function testUserRegistersExpectedRoutes(): void {
        // region Arrange.
        $router = new Router(
                Phake::mock(DiskController::class),
                Phake::mock(UserController::class),
                Phake::mock(ApiController::class),
                new AuthMiddleware()
        );
        $app = AppFactory::create();
        $app->group('/user', function (RouteCollectorProxy $group) use ($router): void {
            $router->user($group);
        });
        // endregion.

        // region Act.
        /** @var array<int, Route> $routes */
        $routes = array_values($app->getRouteCollector()->getRoutes());
        // endregion.

        // region Assert.
        $this->assertCount(4, $routes);
        $this->assertSame('/user/login', $routes[0]->getPattern());
        $this->assertSame('login', $routes[0]->getName());
        $this->assertSame('/user/logout', $routes[3]->getPattern());
        $this->assertSame('logout', $routes[3]->getName());
        // endregion.
    }

    public function testApiRegistersExpectedRoutes(): void {
        // region Arrange.
        $router = new Router(
                Phake::mock(DiskController::class),
                Phake::mock(UserController::class),
                Phake::mock(ApiController::class),
                new AuthMiddleware()
        );
        $app = AppFactory::create();
        $app->group('/api', function (RouteCollectorProxy $group) use ($router): void {
            $router->api($group);
        });
        // endregion.

        // region Act.
        /** @var array<int, Route> $routes */
        $routes = array_values($app->getRouteCollector()->getRoutes());
        // endregion.

        // region Assert.
        $this->assertCount(1, $routes);
        $this->assertSame('/api/share', $routes[0]->getPattern());
        $this->assertSame('share', $routes[0]->getName());
        // endregion.
    }
}
