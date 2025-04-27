<?php

declare(strict_types=1);

namespace app\test\entity;

use app\entity\User;
use app\test\support\BaseTestCase;
use DateTime;

class UserTest extends BaseTestCase {
    public function testConstructStoresProperties(): void {
        // region Arrange.
        $lastLogin = new DateTime('2025-01-10 10:00:00');
        $registerDate = new DateTime('2025-01-01 10:00:00');
        // endregion.

        // region Act.
        $user = new User(1, 'tester', 'tester@example.com', 'hash', $lastLogin, $registerDate);
        // endregion.

        // region Assert.
        $this->assertSame(1, $user->id);
        $this->assertSame('tester', $user->login);
        $this->assertSame('tester@example.com', $user->email);
        $this->assertSame('hash', $user->password);
        $this->assertSame($lastLogin, $user->lastLogin);
        $this->assertSame($registerDate, $user->registerDate);
        // endregion.
    }
}
