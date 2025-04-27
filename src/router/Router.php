<?php


namespace app\router;


use app\controller\DiskController;
use app\controller\ApiController;
use app\controller\UserController;
use app\middleware\AuthMiddleware;
use Slim\Routing\RouteCollectorProxy;
use Twig\Environment;


class Router {
    protected Environment $twig;

    function __construct(
            protected readonly DiskController $diskController,
            protected readonly UserController $userController,
            protected readonly ApiController  $apiController,
            protected readonly AuthMiddleware $authMiddleware
    ) {
    }

    function disk(RouteCollectorProxy $group): void {
        $group->get('/', $this->diskController->files(...))
                ->setName('files')->add($this->authMiddleware);

        $group->post('/uploadFile', $this->diskController->uploadFile(...))
                ->setName('uploadFile')->add($this->authMiddleware);

        $group->post('/createFolder', $this->diskController->createFolder(...))
                ->setName('createFolder')->add($this->authMiddleware);

        $group->get('/statistics', $this->diskController->statistics(...))
                ->setName('statistics')->add($this->authMiddleware);

        $group->get('/trash', $this->diskController->trash(...))
                ->setName('trash')->add($this->authMiddleware);

        $group->post('/trash/deleteFile', $this->diskController->deleteFile(...))
                ->setName('deleteFile')->add($this->authMiddleware);

        $group->post('/trash/restoreFile', $this->diskController->restoreFile(...))
                ->setName('restoreFile')->add($this->authMiddleware);

        $group->post('/trash/deleteFolder', $this->diskController->deleteFolder(...))
                ->setName('deleteFolder')->add($this->authMiddleware);

        $group->post('/trash/restoreFolder', $this->diskController->restoreFolder(...))
                ->setName('restoreFolder')->add($this->authMiddleware);

        $group->post('/trash/clear', $this->diskController->clearTrash(...))
                ->setName('clearTrash')->add($this->authMiddleware);

        $group->post('/download', $this->diskController->download(...))
                ->setName('download');

        $group->post('/share', $this->diskController->share(...))
                ->setName('share')->add($this->authMiddleware);

        $group->post('/move', $this->diskController->move(...))
                ->setName('move')->add($this->authMiddleware);

        $group->post('/copy', $this->diskController->copy(...))
                ->setName('copy')->add($this->authMiddleware);

        $group->post('/delete', $this->diskController->delete(...))
                ->setName('delete')->add($this->authMiddleware);

        $group->get('/folders', $this->diskController->getFolders(...))
                ->setName('folders')->add($this->authMiddleware);
    }

    function user(RouteCollectorProxy $group): void {
        $group->get('/login', $this->userController->login(...))
                ->setName('login');

        $group->post('/authorize', $this->userController->authorize(...))
                ->setName('authorize');

        $group->post('/register', $this->userController->register(...))
                ->setName('register');

        $group->post('/logout', $this->userController->logout(...))
                ->setName('logout');
    }

    function api(RouteCollectorProxy $group): void {
        $group->get('/share', $this->apiController->share(...))
                ->setName('share');
    }
}
