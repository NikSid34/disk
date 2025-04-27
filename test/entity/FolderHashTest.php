<?php

declare(strict_types=1);

namespace app\test\entity;

use app\entity\FolderHash;
use app\test\support\BaseTestCase;

class FolderHashTest extends BaseTestCase {
    public function testConstructStoresProperties(): void {
        // region Arrange.
        // endregion.

        // region Act.
        $folderHash = new FolderHash(1, 21, 'folder-hash');
        // endregion.

        // region Assert.
        $this->assertSame(1, $folderHash->id);
        $this->assertSame(21, $folderHash->folderId);
        $this->assertSame('folder-hash', $folderHash->hash);
        // endregion.
    }
}
