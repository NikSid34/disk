<?php

declare(strict_types=1);

namespace app\test\entity;

use app\entity\Breadcrumb;
use app\test\support\BaseTestCase;

class BreadcrumbTest extends BaseTestCase {
    public function testConstructStoresProperties(): void {
        // region Arrange.
        // endregion.

        // region Act.
        $breadcrumb = new Breadcrumb('hash', 'Documents');
        // endregion.

        // region Assert.
        $this->assertSame('hash', $breadcrumb->hash);
        $this->assertSame('Documents', $breadcrumb->name);
        // endregion.
    }
}
