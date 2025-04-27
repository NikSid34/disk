<?php

declare(strict_types=1);

namespace app\test\service;

use app\enum\FileType;
use app\service\AiService;
use app\service\ServiceException;
use app\test\support\BaseTestCase;
use app\test\support\TestEntityFactory;

class AiServiceTest extends BaseTestCase {
    public function testInstanceReturnsService(): void {
        // region Arrange.
        // endregion.

        // region Act.
        $service = AiService::instance();
        // endregion.

        // region Assert.
        $this->assertInstanceOf(AiService::class, $service);
        // endregion.
    }

    public function testGetImageDescriptionThrowsForWrongFileType(): void {
        // region Arrange.
        $service = new AiService();
        $file = TestEntityFactory::createFile(['fileType' => FileType::Pdf, 'name' => 'report.pdf', 'extension' => 'pdf']);
        // endregion.

        // region Act.
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Wrong file type');
        $service->getImageDescription($file);
        // endregion.
    }

    /**
     * @throws ServiceException
     */
    public function testGetImageDescriptionReturnsResponseContent(): void {
        // region Arrange.
        $service = new class extends AiService {
            protected function requestChatCompletion(array $data): string {
                return json_encode([
                        'choices' => [
                                ['message' => ['content' => 'Краткое описание']],
                        ],
                ], JSON_UNESCAPED_UNICODE);
            }
        };
        $path = $this->createTemporaryDirectory('simpledisk-ai-image-') . '/image.jpg';
        $image = imagecreatetruecolor(10, 10);
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        $file = TestEntityFactory::createFile([
                'name' => 'photo.jpg',
                'extension' => 'jpg',
                'fileType' => FileType::Image,
                'fileObject' => TestEntityFactory::createFileObject([
                        'storagePath' => $path,
                        'contentType' => 'image/jpeg',
                ]),
        ]);
        // endregion.

        // region Act.
        $description = $service->getImageDescription($file);
        // endregion.

        // region Assert.
        $this->assertSame('Краткое описание', $description);
        // endregion.
    }

    /**
     * @throws ServiceException
     */
    public function testGetDocumentDescriptionReturnsTextSummary(): void {
        // region Arrange.
        $service = new class extends AiService {
            protected function requestChatCompletion(array $data): string {
                return json_encode([
                        'choices' => [
                                ['message' => ['content' => 'Краткое описание']],
                        ],
                ], JSON_UNESCAPED_UNICODE);
            }
        };
        $path = $this->createTemporaryFile('Описание документа', '.txt');
        $file = TestEntityFactory::createFile([
                'name' => 'notes.txt',
                'extension' => 'txt',
                'fileType' => FileType::Text,
                'fileObject' => TestEntityFactory::createFileObject([
                        'storagePath' => $path,
                        'contentType' => 'text/plain',
                ]),
        ]);
        // endregion.

        // region Act.
        $description = $service->getDocumentDescription($file);
        // endregion.

        // region Assert.
        $this->assertSame('Краткое описание', $description);
        // endregion.
    }

    public function testGetDocumentDescriptionThrowsForWrongTypeAndEmptyText(): void {
        // region Arrange.
        $service = new AiService();
        $wrongTypeFile = TestEntityFactory::createFile(['fileType' => FileType::Audio, 'name' => 'track.mp3', 'extension' => 'mp3']);
        $emptyTextPath = $this->createTemporaryFile('', '.txt');
        $emptyTextFile = TestEntityFactory::createFile([
                'name' => 'empty.txt',
                'extension' => 'txt',
                'fileType' => FileType::Text,
                'fileObject' => TestEntityFactory::createFileObject([
                        'storagePath' => $emptyTextPath,
                        'contentType' => 'text/plain',
                ]),
        ]);
        // endregion.

        // region Act.
        try {
            $service->getDocumentDescription($wrongTypeFile);
            $this->fail('Expected ServiceException for wrong file type');
        } catch (ServiceException $e) {
            // region Assert.
            $this->assertSame('Wrong file type', $e->getMessage());
            // endregion.
        }

        try {
            $service->getDocumentDescription($emptyTextFile);
            $this->fail('Expected ServiceException for empty text');
        } catch (ServiceException $e) {
            // region Assert.
            $this->assertSame('Failed to extract text from document', $e->getMessage());
            // endregion.
        }
    }

    public function testGetTextEmbeddingValidatesInputAndResponse(): void {
        // region Arrange.
        $service = new class extends AiService {
            protected function requestEmbedding(array $data): string {
                return json_encode([
                        'data' => [
                                ['embedding' => [0.1, 0.2, 0.3]],
                        ],
                ], JSON_UNESCAPED_UNICODE);
            }
        };
        // endregion.

        // region Act.
        try {
            $service->getTextEmbedding('');
            $this->fail('Expected ServiceException for empty text');
        } catch (ServiceException $e) {
            // region Assert.
            $this->assertSame('Text must not be empty', $e->getMessage());
            // endregion.
        }

        $embedding = $service->getTextEmbedding('simple text');
        // endregion.

        // region Assert.
        $this->assertSame([0.1, 0.2, 0.3], $embedding);
        // endregion.
    }

    public function testGetTextEmbeddingThrowsForInvalidJsonResponse(): void {
        // region Arrange.
        $service = new class extends AiService {
            protected function requestEmbedding(array $data): string {
                return '{"data":';
            }
        };
        // endregion.

        // region Act.
        try {
            $service->getTextEmbedding('text');
            $this->fail('Expected ServiceException for invalid response');
        } catch (ServiceException $e) {
            // region Assert.
            $this->assertStringStartsWith('Invalid JSON response:', $e->getMessage());
            // endregion.
        }
    }
}
