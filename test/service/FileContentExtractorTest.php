<?php

declare(strict_types=1);

namespace app\test\service;

use app\enum\FileType;
use app\service\FileContentExtractor;
use app\service\ServiceException;
use app\test\support\BaseTestCase;
use app\test\support\TestEntityFactory;

class FileContentExtractorTest extends BaseTestCase {
    protected function setUp(): void {
        parent::setUp();

        putenv('SIMPLEDISK_ANTIWORD_PATH');
        putenv('SIMPLEDISK_ANTIWORD_MAPPING');
    }

    protected function tearDown(): void {
        putenv('SIMPLEDISK_ANTIWORD_PATH');
        putenv('SIMPLEDISK_ANTIWORD_MAPPING');

        parent::tearDown();
    }

    /**
     * @throws ServiceException
     */
    public function testExtractDocUsesConfiguredAntiwordPathAndMapping(): void {
        // region Arrange.
        $antiwordPath = $this->createTemporaryFile('', '.exe');
        $docPath = $this->createTemporaryFile('doc contents', '.doc');
        $service = new class($antiwordPath, 'UTF-8.txt') extends FileContentExtractor {
            public array $calls = [];

            protected function runAntiword(string $antiword, string $mapping, string $filePath): array {
                $this->calls[] = [$antiword, $mapping, $filePath];

                return [0, " extracted text \n", ''];
            }
        };
        $file = TestEntityFactory::createFile([
                'name' => 'document.doc',
                'extension' => 'doc',
                'fileType' => FileType::Word,
                'fileObject' => TestEntityFactory::createFileObject([
                        'storagePath' => $docPath,
                ]),
        ]);
        // endregion.

        // region Act.
        $text = $service->extract($file);
        // endregion.

        // region Assert.
        $this->assertSame('extracted text', $text);
        $this->assertSame([[$antiwordPath, 'UTF-8.txt', $docPath]], $service->calls);
        // endregion.
    }

    public function testExtractDocIncludesAntiwordFailureDetails(): void {
        // region Arrange.
        $antiwordPath = $this->createTemporaryFile('', '.exe');
        $docPath = $this->createTemporaryFile('doc contents', '.doc');
        $service = new class($antiwordPath, 'UTF-8.txt') extends FileContentExtractor {
            protected function runAntiword(string $antiword, string $mapping, string $filePath): array {
                return [1, '', 'mapping missing'];
            }
        };
        $file = TestEntityFactory::createFile([
                'name' => 'document.doc',
                'extension' => 'doc',
                'fileType' => FileType::Word,
                'fileObject' => TestEntityFactory::createFileObject([
                        'storagePath' => $docPath,
                ]),
        ]);
        // endregion.

        // region Act.
        $this->expectException(ServiceException::class);
        $this->expectExceptionMessage('Failed to extract DOC file: mapping missing');
        $service->extract($file);
        // endregion.
    }
}
