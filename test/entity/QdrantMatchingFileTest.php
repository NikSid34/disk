<?php

declare(strict_types=1);

namespace app\test\entity;

use app\entity\QdrantMatchingFile;
use app\test\support\BaseTestCase;

class QdrantMatchingFileTest extends BaseTestCase {
    public function testConstructStoresProperties(): void {
        // region Arrange.
        // endregion.

        // region Act.
        $file = new QdrantMatchingFile('hash', 87.44);
        // endregion.

        // region Assert.
        $this->assertSame('hash', $file->hash);
        $this->assertSame(87.44, $file->matchPercentage);
        // endregion.
    }
}
