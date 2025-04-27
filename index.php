<?php


use app\container\AppContainer;
use app\middleware\AuthMiddleware;
use app\router\Router;
use Slim\Factory\AppFactory;


require 'vendor/autoload.php';
\session_start();

$app = AppFactory::create();
$container = AppContainer::fromDefaultConfig();

try {
    $router = new Router(
            $container->diskController(),
            $container->userController(),
            $container->apiController(),
            new AuthMiddleware()
    );
} catch (Exception $e) {
    echo 'Failed to create router';
    exit(1);
}

$app->group('/disk', [$router, 'disk']);
$app->group('/user', [$router, 'user']);
$app->group('/api', [$router, 'api']);
$app->run();
