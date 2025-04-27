<?php

declare(strict_types=1);

namespace app\test\entity;

use app\test\support\BaseTestCase;
use DateTime;

class FolderTest extends BaseTestCase {
    public function testConstructStoresProperties(): void {
        // region Arrange.
        $createdAt = new DateTime('2025-01-01 10:00:00');
        $lastVisited = new DateTime('2025-01-02 10:00:00');
        // endregion.

        // region Act.
        $folder = new \app\entity\Folder(3, 'hash', 5, 8, 'Photos', $createdAt, 7, $lastVisited, true);
        // endregion.

        // region Assert.
        $this->assertSame(3, $folder->id);
        $this->assertSame('hash', $folder->hash);
        $this->assertSame(5, $folder->userId);
        $this->assertSame(8, $folder->parentId);
        $this->assertSame('Photos', $folder->name);
        $this->assertSame($createdAt, $folder->createdAt);
        $this->assertSame(7, $folder->visitsNum);
        $this->assertSame($lastVisited, $folder->lastVisited);
        $this->assertTrue($folder->isPublic);
        // endregion.
    }
}
