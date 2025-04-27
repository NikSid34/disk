<?php

declare(strict_types=1);

namespace app\test\entity;

use app\enum\FileType;
use app\test\support\BaseTestCase;
use app\test\support\TestEntityFactory;
use DateTime;

class FileTest extends BaseTestCase {
    public function testConstructStoresProperties(): void {
        // region Arrange.
        $fileObject = TestEntityFactory::createFileObject();
        $thumbnail = TestEntityFactory::createFileObject(['id' => 11, 'hash' => 'thumb-hash']);
        $createdAt = new DateTime('2025-01-01 12:00:00');
        // endregion.

        // region Act.
        $file = new \app\entity\File(
                5,
                'file-hash',
                7,
                9,
                'photo.jpg',
                'jpg',
                FileType::Image,
                $createdAt,
                $fileObject,
                $thumbnail,
                12,
                true
        );
        // endregion.

        // region Assert.
        $this->assertSame(5, $file->id);
        $this->assertSame('file-hash', $file->hash);
        $this->assertSame(7, $file->userId);
        $this->assertSame(9, $file->folderId);
        $this->assertSame('photo.jpg', $file->name);
        $this->assertSame('jpg', $file->extension);
        $this->assertSame(FileType::Image, $file->fileType);
        $this->assertSame($createdAt, $file->createdAt);
        $this->assertSame($fileObject, $file->fileObject);
        $this->assertSame($thumbnail, $file->thumbnail);
        $this->assertSame(12, $file->visitsNum);
        $this->assertTrue($file->isPublic);
        // endregion.
    }
}
