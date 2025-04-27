<?php

declare(strict_types=1);

namespace app\test\enum;

use app\enum\ThumbnailQualityLevel;
use app\test\support\BaseTestCase;

class ThumbnailQualityLevelTest extends BaseTestCase {
    public function testContainsExpectedQualityLevels(): void {
        // region Arrange.
        // endregion.

        // region Act.
        $cases = ThumbnailQualityLevel::cases();
        // endregion.

        // region Assert.
        $this->assertSame([ThumbnailQualityLevel::Low, ThumbnailQualityLevel::Medium], $cases);
        $this->assertSame(100, ThumbnailQualityLevel::Low->value);
        $this->assertSame(200, ThumbnailQualityLevel::Medium->value);
        // endregion.
    }
}
