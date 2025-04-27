<?php

declare(strict_types=1);

namespace app\test\controller;

use app\container\AppContainer;
use app\controller\ApiController;
use app\repository\UserRepository;
use app\service\FileService;
use app\service\FolderService;
use app\test\support\BaseTestCase;
use app\test\support\FakePdo;
use app\test\support\TestEntityFactory;
use Phake;

class ApiControllerTest extends BaseTestCase {
    public function testInstanceReturnsController(): void {
        // region Arrange.
        $container = new AppContainer('config_example.json');
        $this->setPrivateProperty($container, 'pdo', new FakePdo());
        $this->setPrivateProperty(AppContainer::class, 'default', $container);
        // endregion.

        // region Act.
        $controller = ApiController::instance();
        // endregion.

        // region Assert.
        $this->assertInstanceOf(ApiController::class, $controller);
        // endregion.
    }

    public function testShareRendersFolderPublicPage(): void {
        // region Arrange.
        $fileService = Phake::mock(FileService::class);
        $folderService = Phake::mock(FolderService::class);
        $folder = TestEntityFactory::createFolder([
                'hash' => 'folder-hash',
                'name' => 'Public Folder',
                'isPublic' => true,
        ]);
        Phake::when($folderService)->getFolderByHash('folder-hash')->thenReturn($folder);
        Phake::when($fileService)->getUserFiles(5, 'folder-hash', '', '', '')->thenReturn([]);
        $controller = new ApiController($fileService, $folderService);
        $request = $this->createRequest('GET', '/api/share', ['hash' => 'folder-hash']);
        $response = $this->createResponse();
        // endregion.

        // region Act.
        $result = $controller->share($request, $response);
        // endregion.

        // region Assert.
        $this->assertSame(200, $result->getStatusCode());
        $this->assertNotSame('', $this->getResponseBody($result));
        Phake::verify($folderService)->increasePublicCounter('folder-hash');
        // endregion.
    }

    public function testShareRendersFilePublicPageWhenFolderMissing(): void {
        // region Arrange.
        $fileService = Phake::mock(FileService::class);
        $folderService = Phake::mock(FolderService::class);
        $file = TestEntityFactory::createFile([
                'hash' => 'file-hash',
                'name' => 'notes.txt',
                'isPublic' => true,
        ]);
        Phake::when($folderService)->getFolderByHash('file-hash')->thenReturn(null);
        Phake::when($fileService)->getFileByHash('file-hash')->thenReturn($file);
        $controller = new ApiController($fileService, $folderService);
        $request = $this->createRequest('GET', '/api/share', ['hash' => 'file-hash']);
        $response = $this->createResponse();
        // endregion.

        // region Act.
        $result = $controller->share($request, $response);
        // endregion.

        // region Assert.
        $this->assertSame(200, $result->getStatusCode());
        $this->assertNotSame('', $this->getResponseBody($result));
        Phake::verify($fileService)->increasePublicCounter('file-hash');
        // endregion.
    }
}
