<?php

declare(strict_types=1);

namespace app\test\service;

use app\entity\Breadcrumb;
use app\repository\FileRepository;
use app\repository\FolderRepository;
use app\service\FileLifecycleService;
use app\service\FolderService;
use app\service\ServiceException;
use app\test\support\BaseTestCase;
use app\test\support\TestEntityFactory;
use Phake;

class FolderServiceTest extends BaseTestCase {
    public function testCreateBuildsHashForRootAndNestedFolder(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 5;
        $folderRepository = Phake::mock(FolderRepository::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);

        Phake::when($folderRepository)->create(
                hash('sha256', '5::Root'),
                5,
                null,
                'Root'
        )->thenReturn(10);

        $parentFolder = TestEntityFactory::createFolder([
                'id' => 7,
                'hash' => 'parent-hash',
                'userId' => 5,
        ]);
        Phake::when($folderRepository)->findByHash('parent-hash')->thenReturn($parentFolder);
        Phake::when($folderRepository)->create(
                hash('sha256', '5:7:Child'),
                5,
                7,
                'Child'
        )->thenReturn(11);

        $service = new FolderService($folderRepository, $fileRepository, $fileLifecycleService);
        // endregion.

        // region Act.
        $rootFolderId = $service->create(5, 'Root', null);
        $childFolderId = $service->create(5, 'Child', 'parent-hash');
        // endregion.

        // region Assert.
        $this->assertSame(10, $rootFolderId);
        $this->assertSame(11, $childFolderId);
        // endregion.
    }

    public function testCreateWrapsMissingParentErrors(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 5;
        $folderRepository = Phake::mock(FolderRepository::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);
        Phake::when($folderRepository)->findByHash('missing-parent')->thenReturn(null);
        $service = new FolderService($folderRepository, $fileRepository, $fileLifecycleService);
        // endregion.

        // region Act.
        try {
            $service->create(5, 'Child', 'missing-parent');
            $this->fail('Expected ServiceException was not thrown');
        } catch (ServiceException $e) {
            // region Assert.
            $this->assertSame('Failed to create folder', $e->getMessage());
            $this->assertInstanceOf(ServiceException::class, $e->getPrevious());
            // endregion.
        }
    }

    public function testGetFolderByHashHonorsPermissions(): void {
        // region Arrange.
        $folderRepository = Phake::mock(FolderRepository::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);
        $privateFolder = TestEntityFactory::createFolder([
                'hash' => 'private-hash',
                'userId' => 5,
                'isPublic' => false,
        ]);
        $publicFolder = TestEntityFactory::createFolder([
                'hash' => 'public-hash',
                'userId' => 8,
                'isPublic' => true,
        ]);
        Phake::when($folderRepository)->findByHash('private-hash')->thenReturn($privateFolder);
        Phake::when($folderRepository)->findByHash('public-hash')->thenReturn($publicFolder);
        Phake::when($folderRepository)->findByHash('missing')->thenReturn(null);
        $service = new FolderService($folderRepository, $fileRepository, $fileLifecycleService);
        // endregion.

        // region Act.
        $_SESSION['USER_ID'] = 5;
        $ownFolder = $service->getFolderByHash('private-hash');

        $_SESSION['USER_ID'] = 6;
        $forbiddenFolder = $service->getFolderByHash('private-hash');
        $publiclyVisibleFolder = $service->getFolderByHash('public-hash');
        $missingFolder = $service->getFolderByHash('missing');
        // endregion.

        // region Assert.
        $this->assertSame($privateFolder, $ownFolder);
        $this->assertNull($forbiddenFolder);
        $this->assertSame($publicFolder, $publiclyVisibleFolder);
        $this->assertNull($missingFolder);
        // endregion.
    }

    public function testGetUserFoldersAndTrashedFoldersNormalizeArguments(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 5;
        $folderRepository = Phake::mock(FolderRepository::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);

        $parentFolder = TestEntityFactory::createFolder([
                'id' => 7,
                'hash' => 'parent-hash',
                'userId' => 5,
        ]);
        Phake::when($folderRepository)->findByHash('parent-hash')->thenReturn($parentFolder);

        $folders = [TestEntityFactory::createFolder(['hash' => 'child-hash'])];
        $trashedFolders = [TestEntityFactory::createFolder(['hash' => 'trash-hash'])];
        Phake::when($folderRepository)->findUserFolders(5, 7, 'total_size', 'ASC', 'photo')->thenReturn($folders);
        Phake::when($folderRepository)->findTrashedFolders(5, 7, 'f.CREATED_AT', 'DESC')->thenReturn($trashedFolders);

        $service = new FolderService($folderRepository, $fileRepository, $fileLifecycleService);
        // endregion.

        // region Act.
        $userFolders = $service->getUserFolders(5, 'parent-hash', 'size', 'asc', 'photo');
        $trashed = $service->getTrashedFolders(5, 'parent-hash');
        // endregion.

        // region Assert.
        $this->assertSame($folders, $userFolders);
        $this->assertSame($trashedFolders, $trashed);
        // endregion.
    }

    public function testGetUserPublicFoldersGetFolderNameAndBreadcrumbs(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 5;
        $folderRepository = Phake::mock(FolderRepository::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);

        $currentFolder = TestEntityFactory::createFolder([
                'id' => 10,
                'hash' => 'current-hash',
                'userId' => 5,
                'parentId' => 9,
                'name' => 'Current',
        ]);

        $publicFolders = [TestEntityFactory::createFolder(['hash' => 'public-hash', 'isPublic' => true])];
        Phake::when($folderRepository)->findPublicByUser(5)->thenReturn($publicFolders);
        Phake::when($folderRepository)->findNameByHashAndUser('current-hash', 5)->thenReturn('Current');
        Phake::when($folderRepository)->findByHash('current-hash')->thenReturn($currentFolder);
        Phake::when($folderRepository)->findByIdAndUser(9, 5)->thenReturn([
                'HASH' => 'parent-hash',
                'NAME' => 'Parent',
                'PARENT_ID' => 8,
        ]);
        Phake::when($folderRepository)->findByIdAndUser(8, 5)->thenReturn([
                'HASH' => 'root-hash',
                'NAME' => 'Root',
                'PARENT_ID' => null,
        ]);
        $service = new FolderService($folderRepository, $fileRepository, $fileLifecycleService);
        // endregion.

        // region Act.
        $resultPublicFolders = $service->getUserPublicFolders(5);
        $folderName = $service->getFolderName('current-hash', 5);
        $breadcrumbs = $service->getBreadcrumbs('current-hash', 5);
        // endregion.

        // region Assert.
        $this->assertSame($publicFolders, $resultPublicFolders);
        $this->assertSame('Current', $folderName);
        $this->assertCount(3, $breadcrumbs);
        $this->assertContainsOnlyInstancesOf(Breadcrumb::class, $breadcrumbs);
        $this->assertSame(FolderService::BASE_FOLDER_NAME, $breadcrumbs[0]->name);
        $this->assertSame('Root', $breadcrumbs[1]->name);
        $this->assertSame('Parent', $breadcrumbs[2]->name);
        // endregion.
    }

    public function testGetFolderNameThrowsWhenFolderDoesNotExist(): void {
        // region Arrange.
        $folderRepository = Phake::mock(FolderRepository::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);
        Phake::when($folderRepository)->findNameByHashAndUser('missing', 5)->thenReturn(null);
        $service = new FolderService($folderRepository, $fileRepository, $fileLifecycleService);
        // endregion.

        // region Act.
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Folder missing does not exist');
        $service->getFolderName('missing', 5);
        // endregion.
    }

    public function testMoveToTrashAndRestoreFromTrashProcessFolderTree(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 5;
        $folderRepository = Phake::mock(FolderRepository::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);

        Phake::when($folderRepository)->findIdByHash('folder-hash', 5, false)->thenReturn(10);
        Phake::when($folderRepository)->findIdByHash('folder-hash', 5, true)->thenReturn(10);
        Phake::when($folderRepository)->findChildFolderIds(10)->thenReturn([11]);
        Phake::when($folderRepository)->findChildFolderIds(11)->thenReturn([]);
        Phake::when($fileRepository)->findFilesByFolderIds([10, 11])->thenReturn([
                ['id' => 21, 'fileObjectId' => 100],
                ['id' => 22, 'fileObjectId' => 101],
        ]);

        $service = new FolderService($folderRepository, $fileRepository, $fileLifecycleService);
        // endregion.

        // region Act.
        $service->moveToTrash('folder-hash');
        $service->restoreFromTrash('folder-hash');
        // endregion.

        // region Assert.
        Phake::verify($folderRepository)->markInTrashByIds([10, 11]);
        Phake::verify($folderRepository)->addToTrashMany([10, 11]);
        Phake::verify($fileRepository)->markInTrashByIds([21, 22]);
        Phake::verify($fileRepository)->addToTrashMany([21, 22]);
        Phake::verify($folderRepository)->restoreFromTrashByIds([10, 11]);
        Phake::verify($folderRepository)->removeFromTrashMany([10, 11]);
        Phake::verify($fileRepository)->restoreFromTrashByIds([21, 22]);
        Phake::verify($fileRepository)->removeFromTrashMany([21, 22]);
        // endregion.
    }

    public function testDeleteForeverShareAndIncreasePublicCounter(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 5;
        $folderRepository = Phake::mock(FolderRepository::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);

        $folder = TestEntityFactory::createFolder([
                'id' => 10,
                'hash' => 'folder-hash',
                'userId' => 5,
        ]);
        $publicFolder = TestEntityFactory::createFolder([
                'id' => 11,
                'hash' => 'public-folder-hash',
                'userId' => 7,
                'isPublic' => true,
        ]);

        Phake::when($folderRepository)->findIdByHash('folder-hash', 5, true)->thenReturn(10);
        Phake::when($folderRepository)->findChildFolderIds(10)->thenReturn([]);
        Phake::when($fileRepository)->findFilesByFolderIds([10])->thenReturn([]);
        Phake::when($folderRepository)->findByHash('folder-hash')->thenReturn($folder);
        Phake::when($folderRepository)->findByHash('public-folder-hash')->thenReturn($publicFolder);

        $service = new FolderService($folderRepository, $fileRepository, $fileLifecycleService);
        // endregion.

        // region Act.
        $service->deleteForever('folder-hash');
        $service->share('folder-hash');

        $_SESSION['USER_ID'] = 6;
        $service->increasePublicCounter('public-folder-hash');
        // endregion.

        // region Assert.
        Phake::verify($fileLifecycleService)->deleteFilesInFolders([10]);
        Phake::verify($folderRepository)->deleteById(10);
        Phake::verify($folderRepository)->markPublic(10);
        Phake::verify($folderRepository)->incrementPublicCounter(11);
        // endregion.
    }
}
