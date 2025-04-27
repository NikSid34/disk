<?php

declare(strict_types=1);

namespace app\test\service;

use app\enum\SubscriptionPlan;
use app\repository\FileRepository;
use app\service\ServiceException;
use app\service\StorageService;
use app\service\UserService;
use app\test\support\BaseTestCase;
use Phake;

class StorageServiceTest extends BaseTestCase {
    public function testGetUserTotalSpaceMatchesSubscriptionPlan(): void {
        // region Arrange.
        $userService = new UserService(Phake::mock(\app\repository\UserRepository::class));
        $fileRepository = Phake::mock(FileRepository::class);
        $service = new StorageService($userService, $fileRepository);
        // endregion.

        // region Act.
        $freeSpace = $service->getUserTotalSpace(1);
        // endregion.

        // region Assert.
        $this->assertSame(10737418240, $freeSpace);
        $this->assertSame(SubscriptionPlan::Base, UserService::getSubscriptionPlan(1));
        // endregion.
    }

    public function testGetUserUsedSpaceWrapsRepositoryError(): void {
        // region Arrange.
        $userService = new UserService(Phake::mock(\app\repository\UserRepository::class));
        $fileRepository = Phake::mock(FileRepository::class);
        Phake::when($fileRepository)->calculateUserUsedSpace(15)->thenThrow(new \RuntimeException('db down'));
        $service = new StorageService($userService, $fileRepository);
        // endregion.

        // region Act.
        try {
            $service->getUserUsedSpace(15);
            $this->fail('Expected ServiceException was not thrown');
        } catch (ServiceException $e) {
            // region Assert.
            $this->assertSame('Failed to calculate used space', $e->getMessage());
            $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
            // endregion.
        }
    }

    public function testGetUserUsedSpaceReturnsRepositoryValue(): void {
        // region Arrange.
        $userService = new UserService(Phake::mock(\app\repository\UserRepository::class));
        $fileRepository = Phake::mock(FileRepository::class);
        Phake::when($fileRepository)->calculateUserUsedSpace(15)->thenReturn(2048);
        $service = new StorageService($userService, $fileRepository);
        // endregion.

        // region Act.
        $usedSpace = $service->getUserUsedSpace(15);
        // endregion.

        // region Assert.
        $this->assertSame(2048, $usedSpace);
        // endregion.
    }
}
