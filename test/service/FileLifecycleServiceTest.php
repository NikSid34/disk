<?php

declare(strict_types=1);

namespace app\test\service;

use app\repository\FileRepository;
use app\service\FileLifecycleService;
use app\service\SearchIndexService;
use app\service\ServiceException;
use app\service\ThumbnailService;
use app\test\support\BaseTestCase;
use app\test\support\TestEntityFactory;
use Phake;
use RuntimeException;

class FileLifecycleServiceTest extends BaseTestCase {
    public function testDeleteTrashedFileThrowsWhenRecordIsMissing(): void {
        // region Arrange.
        $repository = Phake::mock(FileRepository::class);
        $thumbnailService = Phake::mock(ThumbnailService::class);
        Phake::when($repository)->findTrashedFileRecord('missing')->thenReturn(null);
        $service = new FileLifecycleService($repository, $thumbnailService);
        // endregion.

        // region Act.
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Файл не найден в корзине');
        $service->deleteTrashedFile('missing');
        // endregion.
    }

    public function testDeleteFilesInFoldersDeletesEveryFoundFile(): void {
        // region Arrange.
        $repository = Phake::mock(FileRepository::class);
        $thumbnailService = Phake::mock(ThumbnailService::class);
        $searchIndexService = Phake::mock(SearchIndexService::class);
        $service = new FileLifecycleService($repository, $thumbnailService, $searchIndexService);

        $directory = $this->createTemporaryDirectory('simpledisk-file-lifecycle-many-');
        $firstPath = $directory . '/first.txt';
        $secondPath = $directory . '/second.txt';
        file_put_contents($firstPath, 'first');
        file_put_contents($secondPath, 'second');

        $firstObject = TestEntityFactory::createFileObject(['id' => 100, 'hash' => 'first-hash', 'storagePath' => $firstPath]);
        $secondObject = TestEntityFactory::createFileObject(['id' => 200, 'hash' => 'second-hash', 'storagePath' => $secondPath]);

        Phake::when($repository)->findFilesByFolderIds([10, 11])->thenReturn([
                ['id' => 1, 'fileObjectId' => 100, 'hash' => 'first-file-hash'],
                ['id' => 2, 'fileObjectId' => 200, 'hash' => 'second-file-hash'],
        ]);
        Phake::when($repository)->findFileObjectById(100)->thenReturn($firstObject)->thenReturn(null);
        Phake::when($repository)->findFileObjectById(200)->thenReturn($secondObject)->thenReturn(null);
        // endregion.

        // region Act.
        $service->deleteFilesInFolders([10, 11]);
        // endregion.

        // region Assert.
        Phake::verify($repository)->deleteFileById(1);
        Phake::verify($repository)->deleteFileById(2);
        Phake::verify($repository)->deleteFileObjectById(100);
        Phake::verify($repository)->deleteFileObjectById(200);
        Phake::verify($thumbnailService)->deleteCachedThumbnails('first-hash', dirname($firstPath));
        Phake::verify($thumbnailService)->deleteCachedThumbnails('second-hash', dirname($secondPath));
        Phake::verify($searchIndexService)->deleteFile('first-file-hash');
        Phake::verify($searchIndexService)->deleteFile('second-file-hash');
        $this->assertFileDoesNotExist($firstPath);
        $this->assertFileDoesNotExist($secondPath);
        // endregion.
    }

    public function testDeleteFileRecordThrowsWhenObjectIsMissing(): void {
        // region Arrange.
        $repository = Phake::mock(FileRepository::class);
        $thumbnailService = Phake::mock(ThumbnailService::class);
        Phake::when($repository)->findFileObjectById(100)->thenReturn(null);
        $service = new FileLifecycleService($repository, $thumbnailService);
        // endregion.

        // region Act.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File object not found');
        $service->deleteFileRecord(1, 100);
        // endregion.
    }

    public function testDeleteFileRecordKeepsSharedFileObject(): void {
        // region Arrange.
        $repository = Phake::mock(FileRepository::class);
        $thumbnailService = Phake::mock(ThumbnailService::class);
        $directory = $this->createTemporaryDirectory('simpledisk-file-lifecycle-shared-');
        $sourcePath = $directory . '/shared.txt';
        file_put_contents($sourcePath, 'shared');

        $fileObject = TestEntityFactory::createFileObject([
                'id' => 100,
                'hash' => 'shared-hash',
                'storagePath' => $sourcePath,
                'refCount' => 2,
        ]);
        $updatedFileObject = TestEntityFactory::createFileObject([
                'id' => 100,
                'hash' => 'shared-hash',
                'storagePath' => $sourcePath,
                'refCount' => 1,
        ]);

        Phake::when($repository)->findFileObjectById(100)->thenReturn($fileObject)->thenReturn($updatedFileObject);
        $service = new FileLifecycleService($repository, $thumbnailService);
        // endregion.

        // region Act.
        $service->deleteFileRecord(1, 100);
        // endregion.

        // region Assert.
        Phake::verify($repository)->deleteFileById(1);
        Phake::verify($repository)->decrementFileObjectReference(100);
        Phake::verify($repository, Phake::never())->deleteFileObjectById(100);
        Phake::verify($thumbnailService, Phake::never())->deleteCachedThumbnails(Phake::anyParameters());
        $this->assertFileExists($sourcePath);
        // endregion.
    }
}
