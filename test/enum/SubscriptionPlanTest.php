<?php

declare(strict_types=1);

namespace app\test\enum;

use app\enum\SubscriptionPlan;
use app\test\support\BaseTestCase;

class SubscriptionPlanTest extends BaseTestCase {
    public function testContainsExpectedPlans(): void {
        // region Arrange.
        // endregion.

        // region Act.
        $cases = SubscriptionPlan::cases();
        // endregion.

        // region Assert.
        $this->assertSame(
                [SubscriptionPlan::Free, SubscriptionPlan::Base, SubscriptionPlan::Premium, SubscriptionPlan::Ultimate],
                $cases
        );
        // endregion.
    }
}
