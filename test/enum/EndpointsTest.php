<?php

declare(strict_types=1);

namespace app\test\enum;

use app\enum\Endpoints;
use app\test\support\BaseTestCase;

class EndpointsTest extends BaseTestCase {
    public function testContainsExpectedEndpointValues(): void {
        // region Arrange.
        // endregion.

        // region Act.
        $cases = Endpoints::cases();
        // endregion.

        // region Assert.
        $this->assertSame('/disk/', Endpoints::Disk->value);
        $this->assertSame('/disk/createFolder/', Endpoints::CreateFolder->value);
        $this->assertSame('/disk/uploadFile/', Endpoints::UploadFile->value);
        $this->assertCount(3, $cases);
        // endregion.
    }
}
