<?php

declare(strict_types=1);

namespace app\test\entity;

use app\entity\FileObject;
use app\test\support\BaseTestCase;
use DateTime;

class FileObjectTest extends BaseTestCase {
    public function testConstructStoresProperties(): void {
        // region Arrange.
        $createdAt = new DateTime('2025-01-01 10:00:00');
        // endregion.

        // region Act.
        $fileObject = new FileObject(1, 'hash', '/tmp/file.txt', 'text/plain', 512, 2, $createdAt);
        // endregion.

        // region Assert.
        $this->assertSame(1, $fileObject->id);
        $this->assertSame('hash', $fileObject->hash);
        $this->assertSame('/tmp/file.txt', $fileObject->storagePath);
        $this->assertSame('text/plain', $fileObject->contentType);
        $this->assertSame(512, $fileObject->fileSize);
        $this->assertSame(2, $fileObject->refCount);
        $this->assertSame($createdAt, $fileObject->createdAt);
        // endregion.
    }
}
