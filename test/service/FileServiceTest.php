<?php

declare(strict_types=1);

namespace app\test\service;

use app\Application;
use app\entity\QdrantMatchingFile;
use app\enum\FileType;
use app\repository\FileRepository;
use app\service\AiService;
use app\service\FileLifecycleService;
use app\service\FileService;
use app\service\FolderService;
use app\service\QdrantService;
use app\service\SearchIndexService;
use app\service\ServiceException;
use app\service\ThumbnailService;
use app\test\support\BaseTestCase;
use app\test\support\TestEntityFactory;
use Phake;
use RuntimeException;
use Slim\Psr7\UploadedFile;

class FileServiceTest extends BaseTestCase {
    private string $storageDir;

    protected function setUp(): void {
        parent::setUp();

        $this->storageDir = $this->createTemporaryDirectory('simpledisk-storage-');
        putenv('SIMPLEDISK_STORAGE_DIR=' . $this->storageDir);
    }

    protected function tearDown(): void {
        putenv('SIMPLEDISK_STORAGE_DIR');

        parent::tearDown();
    }

    public function testHandleFileUploadCreatesNewObjectWithoutBlockingOnIndexing(): void {
        // region Arrange.
        $folderService = Phake::mock(FolderService::class);
        $aiService = Phake::mock(AiService::class);
        $qdrantService = Phake::mock(QdrantService::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $thumbnailService = Phake::mock(ThumbnailService::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);
        $searchIndexService = Phake::mock(SearchIndexService::class);

        $tempFilePath = $this->createTemporaryFile('Hello world', '.txt');
        $folder = TestEntityFactory::createFolder(['id' => 7, 'hash' => 'folder-hash', 'userId' => 5]);
        $fileObject = TestEntityFactory::createFileObject([
                'id' => 100,
                'hash' => hash_file('sha256', $tempFilePath),
                'storagePath' => sys_get_temp_dir() . '/stored.txt',
                'contentType' => 'text/plain',
                'fileSize' => filesize($tempFilePath),
        ]);
        $storedFile = TestEntityFactory::createFile([
                'id' => 501,
                'hash' => 'stored-file-hash',
                'userId' => 5,
                'folderId' => 7,
                'name' => 'notes.txt',
                'extension' => 'txt',
                'fileType' => FileType::Text,
                'fileObject' => $fileObject,
        ]);

        $uploadedFile = Phake::mock(UploadedFile::class);
        Phake::when($uploadedFile)->getClientFilename()->thenReturn('notes.txt');
        Phake::when($uploadedFile)->getFilePath()->thenReturn($tempFilePath);
        Phake::when($uploadedFile)->getSize()->thenReturn(filesize($tempFilePath));
        Phake::when($uploadedFile)->moveTo(Phake::anyParameters())->thenReturn(null);
        $this->assertSame($this->storageDir, Application::getStorageDir());

        Phake::when($folderService)->getFolderByHash('folder-hash')->thenReturn($folder);
        Phake::when($fileRepository)->findFileObjectByHash(hash_file('sha256', $tempFilePath))->thenReturn(null);
        Phake::when($fileRepository)->createFileObject(Phake::anyParameters())->thenReturn($fileObject);
        Phake::when($fileRepository)->createFile(Phake::anyParameters())->thenReturn(501);
        Phake::when($fileRepository)->findByHash(Phake::capture($createdFileHash))->thenReturn($storedFile);
        Phake::when($thumbnailService)->isPreviewable($storedFile)->thenReturn(false);

        $service = $this->createService(
                $folderService,
                $aiService,
                $qdrantService,
                $fileRepository,
                $thumbnailService,
                $fileLifecycleService,
                $searchIndexService
        );
        // endregion.

        // region Act.
        $fileId = $service->handleFileUpload(5, $uploadedFile, 'folder-hash');
        // endregion.

        // region Assert.
        $this->assertSame(501, $fileId);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $createdFileHash);
        Phake::verify($searchIndexService, Phake::never())->indexFile(Phake::anyParameters());
        Phake::verify($qdrantService, Phake::never())->saveEmbedding(Phake::anyParameters());
        // endregion.
    }

    public function testHandleFileUploadReusesExistingObjectAndIgnoresAuxiliaryFailures(): void {
        // region Arrange.
        $folderService = Phake::mock(FolderService::class);
        $aiService = Phake::mock(AiService::class);
        $qdrantService = Phake::mock(QdrantService::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $thumbnailService = Phake::mock(ThumbnailService::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);
        $searchIndexService = Phake::mock(SearchIndexService::class);

        $directory = $this->createTemporaryDirectory('simpledisk-upload-image-');
        $tempFilePath = $directory . '/photo.jpg';
        $image = imagecreatetruecolor(10, 10);
        imagejpeg($image, $tempFilePath, 90);
        imagedestroy($image);

        $existingFileObject = TestEntityFactory::createFileObject([
                'id' => 200,
                'hash' => hash_file('sha256', $tempFilePath),
                'storagePath' => $tempFilePath,
                'contentType' => 'image/jpeg',
                'fileSize' => filesize($tempFilePath),
                'refCount' => 2,
        ]);
        $storedFile = TestEntityFactory::createFile([
                'id' => 601,
                'hash' => 'image-file-hash',
                'userId' => 5,
                'folderId' => null,
                'name' => 'photo.jpg',
                'extension' => 'jpg',
                'fileType' => FileType::Image,
                'fileObject' => $existingFileObject,
        ]);

        $uploadedFile = Phake::mock(UploadedFile::class);
        Phake::when($uploadedFile)->getClientFilename()->thenReturn('photo.jpg');
        Phake::when($uploadedFile)->getFilePath()->thenReturn($tempFilePath);
        Phake::when($uploadedFile)->getSize()->thenReturn(filesize($tempFilePath));

        Phake::when($fileRepository)->findFileObjectByHash(hash_file('sha256', $tempFilePath))->thenReturn($existingFileObject);
        Phake::when($fileRepository)->findFileObjectById(200)->thenReturn($existingFileObject);
        Phake::when($fileRepository)->createFile(Phake::anyParameters())->thenReturn(601);
        Phake::when($fileRepository)->findByHash(Phake::anyParameters())->thenReturn($storedFile);
        Phake::when($thumbnailService)->isPreviewable($storedFile)->thenReturn(true);
        Phake::when($thumbnailService)->ensureThumbnail(Phake::anyParameters())->thenReturn('/tmp/thumb.jpg');

        $service = $this->createService(
                $folderService,
                $aiService,
                $qdrantService,
                $fileRepository,
                $thumbnailService,
                $fileLifecycleService,
                $searchIndexService
        );
        // endregion.

        // region Act.
        $fileId = $service->handleFileUpload(5, $uploadedFile, null);
        // endregion.

        // region Assert.
        $this->assertSame(601, $fileId);
        Phake::verify($fileRepository)->incrementFileObjectReference(200);
        Phake::verify($thumbnailService)->ensureThumbnail($storedFile, \app\enum\ThumbnailQualityLevel::Low);
        Phake::verify($thumbnailService)->ensureThumbnail($storedFile, \app\enum\ThumbnailQualityLevel::Medium);
        Phake::verify($searchIndexService, Phake::never())->indexFile(Phake::anyParameters());
        Phake::verify($qdrantService, Phake::never())->saveEmbedding(Phake::anyParameters());
        // endregion.
    }

    public function testGetUserFilesSupportsSmartSearchAndPlainSearch(): void {
        // region Arrange.
        $folderService = Phake::mock(FolderService::class);
        $aiService = Phake::mock(AiService::class);
        $qdrantService = Phake::mock(QdrantService::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $thumbnailService = Phake::mock(ThumbnailService::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);
        $searchIndexService = Phake::mock(SearchIndexService::class);

        $folder = TestEntityFactory::createFolder(['id' => 7, 'hash' => 'folder-hash', 'userId' => 5]);
        $fileA = TestEntityFactory::createFile(['hash' => 'hash-a']);
        $fileB = TestEntityFactory::createFile(['hash' => 'hash-b']);

        Phake::when($folderService)->getFolderByHash('folder-hash')->thenReturn($folder);
        Phake::when($aiService)->getTextEmbedding('cat')->thenReturn([0.5]);
        Phake::when($qdrantService)->getMatchedFiles(5, [0.5])->thenReturn([
                new QdrantMatchingFile('hash-b', 81.5),
                new QdrantMatchingFile('hash-a', 95.2),
        ])->thenReturn([]);
        Phake::when($searchIndexService)->searchFileHashes(5, 7, 'photo')->thenReturn(['hash-b']);
        Phake::when($fileRepository)->findUserFiles(5, 7, null, ['hash-b', 'hash-a'], 'f.CREATED_AT', 'DESC')->thenReturn([$fileA, $fileB]);
        Phake::when($fileRepository)->findUserFiles(5, 7, 'photo', null, 'fo.FILE_SIZE', 'ASC')->thenReturn([$fileA]);
        Phake::when($fileRepository)->findUserFiles(5, 7, null, ['hash-b', 'hash-a'], 'fo.FILE_SIZE', 'ASC')->thenReturn([$fileA, $fileB]);

        $service = $this->createService(
                $folderService,
                $aiService,
                $qdrantService,
                $fileRepository,
                $thumbnailService,
                $fileLifecycleService,
                $searchIndexService
        );
        // endregion.

        // region Act.
        $smartSearchResult = $service->getUserFiles(5, 'folder-hash', null, null, 'cat', true);
        $emptySmartSearchResult = $service->getUserFiles(5, 'folder-hash', null, null, 'cat', true);
        $plainSearchResult = $service->getUserFiles(5, 'folder-hash', 'size', 'asc', 'photo');
        // endregion.

        // region Assert.
        $this->assertCount(2, $smartSearchResult);
        $this->assertInstanceOf(\app\entity\MatchedFile::class, $smartSearchResult[0]);
        $this->assertSame('hash-b', $smartSearchResult[0]->hash);
        $this->assertSame(81.5, $smartSearchResult[0]->matchedPercentage);
        $this->assertSame('hash-a', $smartSearchResult[1]->hash);
        $this->assertSame([], $emptySmartSearchResult);
        $this->assertSame([$fileA, $fileB], $plainSearchResult);
        // endregion.
    }

    public function testAggregatedGettersDelegateToRepository(): void {
        // region Arrange.
        $folderService = Phake::mock(FolderService::class);
        $aiService = Phake::mock(AiService::class);
        $qdrantService = Phake::mock(QdrantService::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $thumbnailService = Phake::mock(ThumbnailService::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);
        $publicFiles = [TestEntityFactory::createFile(['hash' => 'public-hash'])];
        $largestFiles = [TestEntityFactory::createFile(['hash' => 'largest-hash'])];
        Phake::when($fileRepository)->countUserFiles(5)->thenReturn(10);
        Phake::when($fileRepository)->countDistinctUserFileObjects(5)->thenReturn(8);
        Phake::when($fileRepository)->findPublicFilesByUser(5)->thenReturn($publicFiles);
        Phake::when($fileRepository)->findLargestFilesByUser(5)->thenReturn($largestFiles);

        $service = $this->createService(
                $folderService,
                $aiService,
                $qdrantService,
                $fileRepository,
                $thumbnailService,
                $fileLifecycleService
        );
        // endregion.

        // region Act.
        $totalFiles = $service->getUserTotalFiles(5);
        $uniqFiles = $service->getUserUniqFiles(5);
        $resultPublicFiles = $service->getUserPublicFiles(5);
        $resultLargestFiles = $service->getTopLargestFiles(5);
        // endregion.

        // region Assert.
        $this->assertSame(10, $totalFiles);
        $this->assertSame(8, $uniqFiles);
        $this->assertSame($publicFiles, $resultPublicFiles);
        $this->assertSame($largestFiles, $resultLargestFiles);
        // endregion.
    }

    public function testCopyFileAndMoveFileValidateEntities(): void {
        // region Arrange.
        $folderService = Phake::mock(FolderService::class);
        $aiService = Phake::mock(AiService::class);
        $qdrantService = Phake::mock(QdrantService::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $thumbnailService = Phake::mock(ThumbnailService::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);

        $file = TestEntityFactory::createFile([
                'userId' => 5,
                'hash' => 'file-hash',
                'fileObject' => TestEntityFactory::createFileObject(['id' => 300]),
        ]);
        $folder = TestEntityFactory::createFolder(['id' => 12, 'hash' => 'target-folder', 'userId' => 5]);

        Phake::when($fileRepository)->findActiveByHashForUser(5, 'file-hash')->thenReturn($file)->thenReturn(null);
        Phake::when($folderService)->getFolderByHash('target-folder')->thenReturn($folder);
        Phake::when($fileRepository)->createFile(Phake::anyParameters())->thenReturn(700);
        Phake::when($fileRepository)->moveActiveFile(5, 'file-hash', 12)->thenReturn(true)->thenReturn(false);

        $service = $this->createService(
                $folderService,
                $aiService,
                $qdrantService,
                $fileRepository,
                $thumbnailService,
                $fileLifecycleService
        );
        // endregion.

        // region Act.
        $copyId = $service->copyFile(5, 'file-hash', 'target-folder');

        try {
            $service->copyFile(5, 'file-hash', 'target-folder');
            $this->fail('Expected ServiceException for missing file');
        } catch (ServiceException) {
            $this->assertTrue(true);
        }

        $service->moveFile(5, 'file-hash', 'target-folder');

        try {
            $service->moveFile(5, 'file-hash', 'target-folder');
            $this->fail('Expected ServiceException for failed move');
        } catch (ServiceException) {
            $this->assertTrue(true);
        }
        // endregion.

        // region Assert.
        $this->assertSame(700, $copyId);
        Phake::verify($fileRepository)->incrementFileObjectReference(300);
        Phake::verify($fileRepository, Phake::times(2))->moveActiveFile(5, 'file-hash', 12);
        Phake::verify($fileRepository)->markFilePendingIndexing('file-hash', 5);
        // endregion.
    }

    public function testTrashLifecycleMethodsUseCurrentUser(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 5;
        $folderService = Phake::mock(FolderService::class);
        $aiService = Phake::mock(AiService::class);
        $qdrantService = Phake::mock(QdrantService::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $thumbnailService = Phake::mock(ThumbnailService::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);

        Phake::when($fileRepository)->findFileIdByHash('file-hash', 5, false)->thenReturn(21);
        Phake::when($fileRepository)->findFileIdByHash('file-hash', 5, true)->thenReturn(21)->thenReturn(null)->thenReturn(21);

        $service = $this->createService(
                $folderService,
                $aiService,
                $qdrantService,
                $fileRepository,
                $thumbnailService,
                $fileLifecycleService
        );
        // endregion.

        // region Act.
        $service->moveToTrash('file-hash');
        $service->restoreFromTrash('file-hash');

        try {
            $service->deleteForever('file-hash');
            $this->fail('Expected ServiceException for missing file in trash');
        } catch (ServiceException $e) {
            $this->assertSame('File not found in trash', $e->getMessage());
        }

        $service->deleteForever('file-hash');
        // endregion.

        // region Assert.
        Phake::verify($fileRepository)->markInTrash('file-hash', 5);
        Phake::verify($fileRepository)->addToTrash(21);
        Phake::verify($fileRepository)->restoreFromTrash('file-hash', 5);
        Phake::verify($fileRepository)->removeFromTrash(21);
        Phake::verify($fileLifecycleService)->deleteTrashedFile('file-hash');
        // endregion.
    }

    public function testGetTrashedFilesAndGetFileByHashRespectPermissions(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 5;
        $folderService = Phake::mock(FolderService::class);
        $aiService = Phake::mock(AiService::class);
        $qdrantService = Phake::mock(QdrantService::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $thumbnailService = Phake::mock(ThumbnailService::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);

        $folder = TestEntityFactory::createFolder(['id' => 7, 'hash' => 'folder-hash', 'userId' => 5]);
        $privateFile = TestEntityFactory::createFile(['userId' => 7, 'isPublic' => false]);
        $publicFile = TestEntityFactory::createFile(['userId' => 7, 'isPublic' => true, 'hash' => 'public-file-hash']);
        $ownFile = TestEntityFactory::createFile(['userId' => 5, 'hash' => 'own-file-hash']);
        $trashedFiles = [TestEntityFactory::createFile(['hash' => 'trash-hash'])];

        Phake::when($folderService)->getFolderByHash('folder-hash')->thenReturn($folder);
        Phake::when($fileRepository)->findTrashedFiles(5, 7, 'f.NAME', 'ASC')->thenReturn($trashedFiles);
        Phake::when($fileRepository)->findByHash('missing')->thenReturn(null);
        Phake::when($fileRepository)->findByHash('private')->thenReturn($privateFile);
        Phake::when($fileRepository)->findByHash('public-file-hash')->thenReturn($publicFile);
        Phake::when($fileRepository)->findByHash('own-file-hash')->thenReturn($ownFile);
        Phake::when($fileRepository)->findByHash('broken')->thenThrow(new RuntimeException('db failure'));

        $service = $this->createService(
                $folderService,
                $aiService,
                $qdrantService,
                $fileRepository,
                $thumbnailService,
                $fileLifecycleService
        );
        // endregion.

        // region Act.
        $resultTrashedFiles = $service->getTrashedFiles(5, 'folder-hash', 'name', 'asc');
        $missingFile = $service->getFileByHash('missing');
        $_SESSION['USER_ID'] = 6;
        $forbiddenFile = $service->getFileByHash('private');
        $publiclyVisibleFile = $service->getFileByHash('public-file-hash');
        $_SESSION['USER_ID'] = 5;
        $ownVisibleFile = $service->getFileByHash('own-file-hash');
        $uncheckedFile = $service->getFileByHash('private', false);
        // endregion.

        // region Assert.
        $this->assertSame($trashedFiles, $resultTrashedFiles);
        $this->assertNull($missingFile);
        $this->assertNull($forbiddenFile);
        $this->assertSame($publicFile, $publiclyVisibleFile);
        $this->assertSame($ownFile, $ownVisibleFile);
        $this->assertSame($privateFile, $uncheckedFile);

        try {
            $service->getFileByHash('broken');
            $this->fail('Expected ServiceException for repository failure');
        } catch (ServiceException $e) {
            $this->assertSame('Failed to get file by hash', $e->getMessage());
        }
        // endregion.
    }

    public function testShareMoveCopyAndIncreasePublicCounterHandleState(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 5;
        $folderService = Phake::mock(FolderService::class);
        $aiService = Phake::mock(AiService::class);
        $qdrantService = Phake::mock(QdrantService::class);
        $fileRepository = Phake::mock(FileRepository::class);
        $thumbnailService = Phake::mock(ThumbnailService::class);
        $fileLifecycleService = Phake::mock(FileLifecycleService::class);

        $file = TestEntityFactory::createFile([
                'id' => 800,
                'hash' => 'file-hash',
                'userId' => 5,
                'folderId' => 9,
                'fileObject' => TestEntityFactory::createFileObject(['id' => 300]),
        ]);
        $foreignPublicFile = TestEntityFactory::createFile([
                'id' => 801,
                'hash' => 'foreign-public',
                'userId' => 7,
                'folderId' => 9,
                'isPublic' => true,
                'fileObject' => TestEntityFactory::createFileObject(['id' => 301]),
        ]);

        Phake::when($fileRepository)->findByHash('missing')->thenReturn(null);
        Phake::when($fileRepository)->findByHash('file-hash')->thenReturn($file);
        Phake::when($fileRepository)->findByHash('foreign-public')->thenReturn($foreignPublicFile);

        $service = $this->createService(
                $folderService,
                $aiService,
                $qdrantService,
                $fileRepository,
                $thumbnailService,
                $fileLifecycleService
        );
        // endregion.

        // region Act.
        try {
            $service->share('missing');
            $this->fail('Expected RuntimeException for missing file');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }

        $service->share('file-hash');
        $service->move('file-hash', 15);
        $service->copy('file-hash');

        try {
            $service->move('file-hash');
            $this->fail('Expected RuntimeException for missing target folder');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        }

        $_SESSION['USER_ID'] = 6;
        $service->increasePublicCounter('foreign-public');

        $_SESSION['USER_ID'] = 5;
        $service->increasePublicCounter('file-hash');

        try {
            $service->increasePublicCounter('missing');
            $this->fail('Expected ServiceException for missing file');
        } catch (ServiceException) {
            $this->assertTrue(true);
        }
        // endregion.

        // region Assert.
        Phake::verify($fileRepository)->markPublic(800);
        Phake::verify($fileRepository)->moveActiveFile(5, 'file-hash', 15);
        Phake::verify($fileRepository)->markFilePendingIndexing('file-hash', 5);
        Phake::verify($fileRepository)->incrementFileObjectReference(300);
        Phake::verify($fileRepository)->createFile(Phake::anyParameters());
        Phake::verify($fileRepository)->incrementPublicCounter(801);
        Phake::verify($fileRepository)->incrementPublicCounter(800);
        // endregion.
    }

    private function createService(
            FolderService $folderService,
            AiService $aiService,
            QdrantService $qdrantService,
            FileRepository $fileRepository,
            ThumbnailService $thumbnailService,
            FileLifecycleService $fileLifecycleService,
            ?SearchIndexService $searchIndexService = null
    ): FileService {
        return new FileService(
                $folderService,
                $aiService,
                $qdrantService,
                $fileRepository,
                $thumbnailService,
                $fileLifecycleService,
                $searchIndexService ?? Phake::mock(SearchIndexService::class)
        );
    }
}
