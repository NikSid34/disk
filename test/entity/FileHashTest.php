<?php

declare(strict_types=1);

namespace app\test\entity;

use app\entity\FileHash;
use app\test\support\BaseTestCase;

class FileHashTest extends BaseTestCase {
    public function testConstructStoresProperties(): void {
        // region Arrange.
        // endregion.

        // region Act.
        $fileHash = new FileHash(1, 15, 'hash-value');
        // endregion.

        // region Assert.
        $this->assertSame(1, $fileHash->id);
        $this->assertSame(15, $fileHash->fileId);
        $this->assertSame('hash-value', $fileHash->hash);
        // endregion.
    }
}
