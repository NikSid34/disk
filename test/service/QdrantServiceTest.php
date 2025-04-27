<?php

declare(strict_types=1);

namespace app\test\service;

use app\service\QdrantService;
use app\service\ServiceException;
use app\test\support\BaseTestCase;

class QdrantServiceTest extends BaseTestCase {
    public function testInstanceReturnsService(): void {
        // region Arrange.
        // endregion.

        // region Act.
        $service = QdrantService::instance();
        // endregion.

        // region Assert.
        $this->assertInstanceOf(QdrantService::class, $service);
        // endregion.
    }

    /**
     * @throws ServiceException
     */
    public function testSaveEmbeddingSendsDataToServer(): void {
        // region Arrange.
        $service = new class extends QdrantService {
            /** @var array<int, array{url: string, method: string, body: string}> */
            public array $requests = [];

            protected function sendJsonRequest(string $url, string $method, string $jsonData): array {
                $this->requests[] = ['url' => $url, 'method' => $method, 'body' => $jsonData];

                return ['body' => '{"status":"ok"}', 'status' => 200];
            }
        };
        // endregion.

        // region Act.
        $service->saveEmbedding(5, 10, 'file-hash', [0.1, 0.2, 0.3]);
        // endregion.

        // region Assert.
        $this->assertCount(1, $service->requests);
        $this->assertSame('PUT', $service->requests[0]['method']);
        $this->assertStringContainsString('"file_hash":"file-hash"', $service->requests[0]['body']);
        // endregion.
    }

    public function testSaveEmbeddingThrowsForEncodingAndServerErrors(): void {
        // region Arrange.
        $service = new class extends QdrantService {
            protected function sendJsonRequest(string $url, string $method, string $jsonData): array {
                return ['body' => '{"status":"error"}', 'status' => 500];
            }
        };
        // endregion.

        // region Act.
        try {
            $service->saveEmbedding(5, 10, 'file-hash', [INF]);
            $this->fail('Expected ServiceException for invalid payload');
        } catch (ServiceException $e) {
            // region Assert.
            $this->assertStringStartsWith('Failed to encode JSON:', $e->getMessage());
            // endregion.
        }

        try {
            $service->saveEmbedding(5, 10, 'file-hash', [0.1, 0.2]);
            $this->fail('Expected ServiceException for server error');
        } catch (ServiceException $e) {
            // region Assert.
            $this->assertStringStartsWith('Failed to save embedding to Qdrant:', $e->getMessage());
            // endregion.
        }
    }

    /**
     * @throws ServiceException
     */
    public function testGetMatchedFilesMapsResponseItems(): void {
        // region Arrange.
        $service = new class extends QdrantService {
            protected function sendJsonRequest(string $url, string $method, string $jsonData): array {
                return [
                        'body' => json_encode([
                                'result' => [
                                        ['payload' => ['file_hash' => 'hash-a'], 'score' => 0.8234],
                                        ['payload' => [], 'score' => 0.9],
                                        ['payload' => ['file_hash' => 'hash-b'], 'score' => 0.4],
                                ],
                        ], JSON_UNESCAPED_UNICODE),
                        'status' => 200,
                ];
            }
        };
        // endregion.

        // region Act.
        $matches = $service->getMatchedFiles(5, [0.1, 0.2, 0.3]);
        // endregion.

        // region Assert.
        $this->assertCount(2, $matches);
        $this->assertSame('hash-a', $matches[0]->hash);
        $this->assertSame(82.34, $matches[0]->matchPercentage);
        $this->assertSame('hash-b', $matches[1]->hash);
        $this->assertSame(40.0, $matches[1]->matchPercentage);
        // endregion.
    }

    public function testGetMatchedFilesThrowsForInvalidResponse(): void {
        // region Arrange.
        $service = new class extends QdrantService {
            protected function sendJsonRequest(string $url, string $method, string $jsonData): array {
                return ['body' => '{"result":', 'status' => 200];
            }
        };
        // endregion.

        // region Act.
        try {
            $service->getMatchedFiles(5, [0.1]);
            $this->fail('Expected ServiceException for invalid JSON');
        } catch (ServiceException $e) {
            // region Assert.
            $this->assertStringStartsWith('Invalid JSON response:', $e->getMessage());
            // endregion.
        }
    }

    /**
     * @throws ServiceException
     */
    public function testInitCollectionCreatesCollectionOnce(): void {
        // region Arrange.
        $service = new class extends QdrantService {
            public int $statusChecks = 0;
            public int $createRequests = 0;

            protected function fetchStatusCode(string $url): int {
                return $this->statusChecks++ === 0 ? 404 : 200;
            }

            protected function sendJsonRequest(string $url, string $method, string $jsonData): array {
                $this->createRequests++;

                return ['body' => '{"status":"ok"}', 'status' => 200];
            }
        };
        // endregion.

        // region Act.
        $service->initCollection();
        $service->initCollection();
        // endregion.

        // region Assert.
        $this->assertSame(2, $service->statusChecks);
        $this->assertSame(1, $service->createRequests);
        // endregion.
    }
}
