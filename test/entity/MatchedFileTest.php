<?php

declare(strict_types=1);

namespace app\test\entity;

use app\enum\FileType;
use app\test\support\BaseTestCase;
use app\test\support\TestEntityFactory;
use DateTime;

class MatchedFileTest extends BaseTestCase {
    public function testConstructStoresProperties(): void {
        // region Arrange.
        $fileObject = TestEntityFactory::createFileObject();
        $createdAt = new DateTime('2025-01-01 11:00:00');
        // endregion.

        // region Act.
        $matchedFile = new \app\entity\MatchedFile(
                1,
                'hash',
                2,
                3,
                'doc.txt',
                'txt',
                FileType::Text,
                $createdAt,
                $fileObject,
                null,
                4,
                true,
                87.5
        );
        // endregion.

        // region Assert.
        $this->assertSame('hash', $matchedFile->hash);
        $this->assertSame(87.5, $matchedFile->matchedPercentage);
        $this->assertTrue($matchedFile->isPublic);
        // endregion.
    }

    public function testFromFileCopiesBaseFileProperties(): void {
        // region Arrange.
        $file = TestEntityFactory::createFile([
                'id' => 15,
                'hash' => 'base-hash',
                'userId' => 8,
                'folderId' => 12,
                'name' => 'report.pdf',
                'extension' => 'pdf',
                'fileType' => FileType::Pdf,
                'isPublic' => true,
        ]);
        // endregion.

        // region Act.
        $matchedFile = \app\entity\MatchedFile::fromFile($file, 55.3);
        // endregion.

        // region Assert.
        $this->assertSame($file->id, $matchedFile->id);
        $this->assertSame($file->hash, $matchedFile->hash);
        $this->assertSame($file->fileObject, $matchedFile->fileObject);
        $this->assertSame(55.3, $matchedFile->matchedPercentage);
        // endregion.
    }
}
