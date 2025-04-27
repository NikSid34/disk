<?php

declare(strict_types=1);

namespace app\test\controller;

use app\container\AppContainer;
use app\controller\DiskController;
use app\repository\UserRepository;
use app\service\FileService;
use app\service\FolderService;
use app\service\StorageService;
use app\service\UserService;
use app\test\support\BaseTestCase;
use app\test\support\FakePdo;
use app\test\support\TestEntityFactory;
use Phake;
use Slim\Psr7\UploadedFile;

class DiskControllerTest extends BaseTestCase {
    public function testInstanceReturnsController(): void {
        // region Arrange.
        $container = new AppContainer('config_example.json');
        $this->setPrivateProperty($container, 'pdo', new FakePdo());
        $this->setPrivateProperty(AppContainer::class, 'default', $container);
        // endregion.

        // region Act.
        $controller = DiskController::instance();
        // endregion.

        // region Assert.
        $this->assertInstanceOf(DiskController::class, $controller);
        // endregion.
    }

    public function testFilesRendersPageAndHandlesErrors(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 5;
        $fileService = Phake::mock(FileService::class);
        $folderService = Phake::mock(FolderService::class);
        $userRepository = Phake::mock(UserRepository::class);
        $storageService = Phake::mock(StorageService::class);
        Phake::when($userRepository)->findLoginById(5)->thenReturn('tester');
        $userService = new UserService($userRepository);
        Phake::when($fileService)->getUserFiles(5, null, 'date', 'desc', '', false)->thenReturn([]);
        Phake::when($folderService)->getUserFolders(5, null, 'date', 'desc', '')->thenReturn([]);
        Phake::when($storageService)->getUserUsedSpace(5)->thenReturn(100);
        Phake::when($storageService)->getUserTotalSpace(5)->thenReturn(200);

        $controller = new DiskController($fileService, $folderService, $userService, $storageService);
        $request = $this->createRequest('GET', '/disk', [
                'sortBy' => 'invalid',
                'sortDirection' => 'invalid',
                'viewMode' => 'invalid',
        ]);
        $response = $this->createResponse();

        $errorFileService = Phake::mock(FileService::class);
        Phake::when($errorFileService)->getUserFiles(Phake::anyParameters())->thenThrow(new \RuntimeException('boom'));
        $errorController = new DiskController($errorFileService, $folderService, $userService, $storageService);
        // endregion.

        // region Act.
        $result = $controller->files($request, $response);
        ob_start();
        $errorResult = $errorController->files($this->createRequest('GET', '/disk'), $this->createResponse());
        ob_end_clean();
        // endregion.

        // region Assert.
        $this->assertSame(200, $result->getStatusCode());
        $this->assertNotSame('', $this->getResponseBody($result));
        $this->assertSame(500, $errorResult->getStatusCode());
        $this->assertNotSame('', $this->getResponseBody($errorResult));
        // endregion.
    }

    public function testUploadFileAndCreateFolderHandleSuccessAndValidation(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 5;
        $fileService = Phake::mock(FileService::class);
        $folderService = Phake::mock(FolderService::class);
        $userRepository = Phake::mock(UserRepository::class);
        Phake::when($userRepository)->findLoginById(5)->thenReturn('tester');
        $userService = new UserService($userRepository);
        $storageService = Phake::mock(StorageService::class);
        $controller = new DiskController($fileService, $folderService, $userService, $storageService);

        $okFile = Phake::mock(UploadedFile::class);
        $badFile = Phake::mock(UploadedFile::class);
        Phake::when($okFile)->getError()->thenReturn(UPLOAD_ERR_OK);
        Phake::when($badFile)->getError()->thenReturn(UPLOAD_ERR_NO_FILE);

        $uploadRequest = $this->createRequest(
                'POST',
                '/disk/uploadFile',
                [],
                ['folderHash' => 'folder-hash'],
                null,
                ['files' => [$okFile, $badFile]]
        );

        $invalidFolderRequest = $this->createRequest('POST', '/disk/createFolder', [], null, json_encode(['name' => '   ']));
        $validFolderRequest = $this->createRequest('POST', '/disk/createFolder', [], null, json_encode(['name' => 'Photos', 'parentHash' => 'parent-hash']));
        // endregion.

        // region Act.
        $uploadResponse = $controller->uploadFile($uploadRequest, $this->createResponse());
        $invalidFolderResponse = $controller->createFolder($invalidFolderRequest, $this->createResponse());
        $validFolderResponse = $controller->createFolder($validFolderRequest, $this->createResponse());
        // endregion.

        // region Assert.
        $this->assertSame(200, $uploadResponse->getStatusCode());
        Phake::verify($fileService)->handleFileUpload(5, $okFile, 'folder-hash');
        $this->assertSame(400, $invalidFolderResponse->getStatusCode());
        $this->assertSame('Invalid folder name', $this->getResponseBody($invalidFolderResponse));
        $this->assertSame(200, $validFolderResponse->getStatusCode());
        Phake::verify($folderService)->create(5, 'Photos', 'parent-hash');
        // endregion.
    }

    public function testStatisticsTrashAndTrashMutationActions(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 5;
        $fileService = Phake::mock(FileService::class);
        $folderService = Phake::mock(FolderService::class);
        $userRepository = Phake::mock(UserRepository::class);
        Phake::when($userRepository)->findLoginById(5)->thenReturn('tester');
        $userService = new UserService($userRepository);
        $storageService = Phake::mock(StorageService::class);

        Phake::when($fileService)->getUserFiles(5, null, null, null, '')->thenReturn([]);
        Phake::when($fileService)->getUserTotalFiles(5)->thenReturn(10);
        Phake::when($fileService)->getUserUniqFiles(5)->thenReturn(8);
        Phake::when($fileService)->getTopLargestFiles(5)->thenReturn([]);
        Phake::when($fileService)->getUserPublicFiles(5)->thenReturn([]);
        Phake::when($folderService)->getUserFolders(5, null, null, null, '')->thenReturn([]);
        Phake::when($folderService)->getUserPublicFolders(5)->thenReturn([]);
        $trashedFiles = [TestEntityFactory::createFile(['hash' => 'trash-file-hash'])];
        $trashedFolders = [TestEntityFactory::createFolder(['hash' => 'trash-folder-hash'])];
        Phake::when($fileService)->getTrashedFiles(5, null, null, null)->thenReturn($trashedFiles);
        Phake::when($folderService)->getTrashedFolders(5, null, null, null)->thenReturn($trashedFolders);
        Phake::when($storageService)->getUserUsedSpace(5)->thenReturn(100);
        Phake::when($storageService)->getUserTotalSpace(5)->thenReturn(200);

        $controller = new DiskController($fileService, $folderService, $userService, $storageService);
        // endregion.

        // region Act.
        $statisticsResponse = $controller->statistics($this->createRequest('GET', '/disk/statistics'), $this->createResponse());
        $trashResponse = $controller->trash($this->createRequest('GET', '/disk/trash'), $this->createResponse());
        $deleteFileResponse = $controller->deleteFile($this->createRequest('POST', '/disk/trash/deleteFile', [], ['hash' => 'file-hash']), $this->createResponse());
        $restoreFileResponse = $controller->restoreFile($this->createRequest('POST', '/disk/trash/restoreFile', [], ['hash' => 'file-hash', 'returnFolderHash' => 'folder-hash']), $this->createResponse());
        $deleteFolderResponse = $controller->deleteFolder($this->createRequest('POST', '/disk/trash/deleteFolder', [], ['hash' => 'folder-hash']), $this->createResponse());
        $restoreFolderResponse = $controller->restoreFolder($this->createRequest('POST', '/disk/trash/restoreFolder', [], ['hash' => 'folder-hash']), $this->createResponse());
        $clearTrashResponse = $controller->clearTrash($this->createRequest('POST', '/disk/trash/clear'), $this->createResponse());
        // endregion.

        // region Assert.
        $this->assertSame(200, $statisticsResponse->getStatusCode());
        $this->assertSame(200, $trashResponse->getStatusCode());
        $this->assertSame(['/disk/trash'], $deleteFileResponse->getHeader('Location'));
        $this->assertSame(['/disk/trash?folder=folder-hash'], $restoreFileResponse->getHeader('Location'));
        $this->assertSame(['/disk/trash'], $deleteFolderResponse->getHeader('Location'));
        $this->assertSame(['/disk/trash'], $restoreFolderResponse->getHeader('Location'));
        $this->assertSame(['/disk/trash'], $clearTrashResponse->getHeader('Location'));
        Phake::verify($fileService)->deleteForever('file-hash');
        Phake::verify($fileService)->deleteForever('trash-file-hash');
        Phake::verify($fileService)->restoreFromTrash('file-hash');
        Phake::verify($folderService)->deleteForever('folder-hash');
        Phake::verify($folderService)->deleteForever('trash-folder-hash');
        Phake::verify($folderService)->restoreFromTrash('folder-hash');
        // endregion.
    }

    public function testDownloadAndShareHandleFolderAndFileBranches(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 5;
        $fileService = Phake::mock(FileService::class);
        $folderService = Phake::mock(FolderService::class);
        $userService = new UserService(Phake::mock(UserRepository::class));
        $storageService = Phake::mock(StorageService::class);
        $controller = new DiskController($fileService, $folderService, $userService, $storageService);

        $folder = TestEntityFactory::createFolder(['hash' => 'folder-hash']);
        $path = $this->createTemporaryFile('download content', '.txt');
        $file = TestEntityFactory::createFile([
                'hash' => 'file-hash',
                'name' => 'download.txt',
                'fileObject' => TestEntityFactory::createFileObject([
                        'storagePath' => $path,
                        'contentType' => 'text/plain',
                ]),
        ]);
        $missingPathFile = TestEntityFactory::createFile([
                'hash' => 'missing-file-hash',
                'name' => 'missing.txt',
                'fileObject' => TestEntityFactory::createFileObject([
                        'storagePath' => $path . '.missing',
                        'contentType' => 'text/plain',
                ]),
        ]);

        Phake::when($folderService)->getFolderByHash('folder-hash')->thenReturn($folder);
        Phake::when($folderService)->getFolderByHash('file-hash')->thenReturn(null);
        Phake::when($folderService)->getFolderByHash('missing-file-hash')->thenReturn(null);
        Phake::when($fileService)->getFileByHash('file-hash', false)->thenReturn($file);
        Phake::when($fileService)->getFileByHash('missing-file-hash', false)->thenReturn($missingPathFile);
        Phake::when($fileService)->getFileByHash('share-file-hash')->thenReturn($file);
        Phake::when($folderService)->getFolderByHash('share-folder-hash')->thenReturn(TestEntityFactory::createFolder(['hash' => 'share-folder-hash']));
        Phake::when($folderService)->getFolderByHash('share-file-hash')->thenReturn(null);
        // endregion.

        // region Act.
        $folderDownload = $controller->download($this->createRequest('POST', '/disk/download', [], ['hash' => 'folder-hash']), $this->createResponse());
        $missingFileDownload = $controller->download($this->createRequest('POST', '/disk/download', [], ['hash' => 'missing-file-hash']), $this->createResponse());
        $fileDownload = $controller->download($this->createRequest('POST', '/disk/download', [], ['hash' => 'file-hash']), $this->createResponse());
        $shareFolder = $controller->share($this->createRequest('POST', '/disk/share', [], ['hash' => 'share-folder-hash']), $this->createResponse());
        $shareFile = $controller->share($this->createRequest('POST', '/disk/share', [], ['hash' => 'share-file-hash']), $this->createResponse());
        // endregion.

        // region Assert.
        $this->assertSame(400, $folderDownload->getStatusCode());
        $this->assertSame(404, $missingFileDownload->getStatusCode());
        $this->assertSame(200, $fileDownload->getStatusCode());
        $this->assertSame(['attachment; filename="download.txt"'], $fileDownload->getHeader('Content-Disposition'));
        $this->assertSame(200, $shareFolder->getStatusCode());
        $this->assertSame(200, $shareFile->getStatusCode());
        $this->assertSame(
                ['link' => '/api/share?hash=share-folder-hash', 'absoluteLink' => '/api/share?hash=share-folder-hash'],
                json_decode($this->getResponseBody($shareFolder), true)
        );
        $this->assertSame(
                ['link' => '/api/share?hash=share-file-hash', 'absoluteLink' => '/api/share?hash=share-file-hash'],
                json_decode($this->getResponseBody($shareFile), true)
        );
        Phake::verify($folderService)->share('share-folder-hash');
        Phake::verify($fileService)->share('share-file-hash');
        // endregion.
    }

    public function testMoveCopyDeleteAndGetFoldersHandleBranches(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 5;
        $fileService = Phake::mock(FileService::class);
        $folderService = Phake::mock(FolderService::class);
        $userRepository = Phake::mock(UserRepository::class);
        Phake::when($userRepository)->findLoginById(5)->thenReturn('tester');
        $userService = new UserService($userRepository);
        $storageService = Phake::mock(StorageService::class);
        $controller = new DiskController($fileService, $folderService, $userService, $storageService);

        $folder = TestEntityFactory::createFolder(['hash' => 'folder-hash']);
        $file = TestEntityFactory::createFile(['hash' => 'file-hash']);
        $folders = [
                TestEntityFactory::createFolder(['id' => 1, 'name' => 'Docs']),
                TestEntityFactory::createFolder(['id' => 2, 'name' => 'Photos']),
        ];

        Phake::when($folderService)->getFolderByHash('folder-hash')->thenReturn($folder)->thenReturn($folder)->thenReturn($folder);
        Phake::when($folderService)->getFolderByHash('file-hash')->thenReturn(null)->thenReturn(null)->thenReturn(null);
        Phake::when($fileService)->getFileByHash('file-hash')->thenReturn($file)->thenReturn($file)->thenReturn($file);
        Phake::when($folderService)->getUserFolders(5, null, null, null, null)->thenReturn($folders);
        // endregion.

        // region Act.
        $moveFolderResponse = $controller->move(
                $this->createRequest('POST', '/disk/move', [], null, json_encode(['hash' => 'folder-hash', 'targetFolderId' => 10])),
                $this->createResponse()
        );
        $moveFileResponse = $controller->move(
                $this->createRequest('POST', '/disk/move', [], null, json_encode(['hash' => 'file-hash', 'targetFolderId' => 10])),
                $this->createResponse()
        );
        $copyFolderResponse = $controller->copy($this->createRequest('POST', '/disk/copy', [], ['hash' => 'folder-hash']), $this->createResponse());
        $copyFileResponse = $controller->copy($this->createRequest('POST', '/disk/copy', [], ['hash' => 'file-hash']), $this->createResponse());
        $deleteFolderResponse = $controller->delete($this->createRequest('POST', '/disk/delete', [], ['hash' => 'folder-hash']), $this->createResponse());
        $deleteFileResponse = $controller->delete($this->createRequest('POST', '/disk/delete', [], ['hash' => 'file-hash']), $this->createResponse());
        $foldersResponse = $controller->getFolders($this->createRequest('GET', '/disk/folders'), $this->createResponse());
        // endregion.

        // region Assert.
        $this->assertSame(400, $moveFolderResponse->getStatusCode());
        $this->assertSame(302, $moveFileResponse->getStatusCode());
        $this->assertSame(400, $copyFolderResponse->getStatusCode());
        $this->assertSame(302, $copyFileResponse->getStatusCode());
        $this->assertSame(302, $deleteFolderResponse->getStatusCode());
        $this->assertSame(302, $deleteFileResponse->getStatusCode());
        $this->assertSame('[{"id":1,"name":"Docs"},{"id":2,"name":"Photos"}]', $this->getResponseBody($foldersResponse));
        Phake::verify($fileService)->move('file-hash', 10);
        Phake::verify($fileService)->copy('file-hash');
        Phake::verify($folderService)->moveToTrash('folder-hash');
        Phake::verify($fileService)->moveToTrash('file-hash');
        // endregion.
    }
}
