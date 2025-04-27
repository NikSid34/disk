<?php

declare(strict_types=1);

namespace app\test\service;

use app\enum\FileType;
use app\repository\FileRepository;
use app\service\AiService;
use app\service\FileIndexingService;
use app\service\QdrantService;
use app\service\SearchIndexService;
use app\test\support\BaseTestCase;
use app\test\support\TestEntityFactory;
use Phake;
use RuntimeException;

class FileIndexingServiceTest extends BaseTestCase {
    public function testProcessPendingFilesIndexesRegularAndSemanticSearchInBackground(): void {
        // region Arrange.
        $repository = Phake::mock(FileRepository::class);
        $aiService = Phake::mock(AiService::class);
        $qdrantService = Phake::mock(QdrantService::class);
        $searchIndexService = Phake::mock(SearchIndexService::class);

        $textFile = TestEntityFactory::createFile([
                'id' => 101,
                'hash' => 'text-hash',
                'name' => 'notes.txt',
                'extension' => 'txt',
                'fileType' => FileType::Text,
        ]);
        $imageFile = TestEntityFactory::createFile([
                'id' => 102,
                'hash' => 'image-hash',
                'name' => 'photo.jpg',
                'extension' => 'jpg',
                'fileType' => FileType::Image,
        ]);

        Phake::when($repository)->claimFilesForIndexing(10)->thenReturn([$textFile, $imageFile]);
        Phake::when($aiService)->getDocumentDescription($textFile)->thenReturn('document summary');
        Phake::when($aiService)->getImageDescription($imageFile)->thenReturn('image summary');
        Phake::when($aiService)->getTextEmbedding('document summary')->thenReturn([0.1, 0.2]);
        Phake::when($aiService)->getTextEmbedding('image summary')->thenReturn([0.3, 0.4]);
        Phake::when($repository)->markFileIndexed(101)->thenReturn(true);
        Phake::when($repository)->markFileIndexed(102)->thenReturn(true);

        $service = new FileIndexingService($repository, $aiService, $qdrantService, $searchIndexService);
        // endregion.

        // region Act.
        $summary = $service->processPendingFiles(10);
        // endregion.

        // region Assert.
        $this->assertSame([
                'claimed' => 2,
                'indexed' => 2,
                'failed' => 0,
                'requeued' => 0,
        ], $summary);
        Phake::verify($searchIndexService)->indexFile($textFile);
        Phake::verify($searchIndexService)->indexFile($imageFile);
        Phake::verify($qdrantService)->saveEmbedding(5, 101, 'text-hash', [0.1, 0.2]);
        Phake::verify($qdrantService)->saveEmbedding(5, 102, 'image-hash', [0.3, 0.4]);
        // endregion.
    }

    public function testProcessPendingFilesRequeuesOnFailureOrConcurrentUpdate(): void {
        // region Arrange.
        $repository = Phake::mock(FileRepository::class);
        $aiService = Phake::mock(AiService::class);
        $qdrantService = Phake::mock(QdrantService::class);
        $searchIndexService = Phake::mock(SearchIndexService::class);

        $failedFile = TestEntityFactory::createFile([
                'id' => 201,
                'hash' => 'failed-hash',
                'name' => 'track.mp3',
                'extension' => 'mp3',
                'fileType' => FileType::Audio,
        ]);
        $requeuedFile = TestEntityFactory::createFile([
                'id' => 202,
                'hash' => 'requeued-hash',
                'name' => 'archive.zip',
                'extension' => 'zip',
                'fileType' => FileType::Archive,
        ]);

        Phake::when($repository)->claimFilesForIndexing(5)->thenReturn([$failedFile, $requeuedFile]);
        Phake::when($searchIndexService)->indexFile($failedFile)->thenThrow(new RuntimeException('Elasticsearch unavailable'));
        Phake::when($repository)->markFileIndexed(202)->thenReturn(false);

        $service = new FileIndexingService($repository, $aiService, $qdrantService, $searchIndexService);
        // endregion.

        // region Act.
        $summary = $service->processPendingFiles(5);
        // endregion.

        // region Assert.
        $this->assertSame([
                'claimed' => 2,
                'indexed' => 0,
                'failed' => 1,
                'requeued' => 1,
        ], $summary);
        Phake::verify($repository)->releaseFileIndexing(201);
        Phake::verify($qdrantService, Phake::never())->saveEmbedding(Phake::anyParameters());
        // endregion.
    }
}
