<?php

declare(strict_types=1);

namespace app\test\service;

use app\enum\SubscriptionPlan;
use app\repository\UserRepository;
use app\service\ServiceException;
use app\service\UserService;
use app\test\support\BaseTestCase;
use Phake;

class UserServiceTest extends BaseTestCase {
    public function testCurrentUserHelpersUseSession(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 10;
        // endregion.

        // region Act.
        $currentUserId = UserService::getCurrentUserId();
        $isAuthorized = UserService::isAuthorized();
        $plan = UserService::getSubscriptionPlan(10);
        // endregion.

        // region Assert.
        $this->assertSame(10, $currentUserId);
        $this->assertTrue($isAuthorized);
        $this->assertSame(SubscriptionPlan::Base, $plan);
        // endregion.
    }

    public function testGetLoginAndExistenceChecksDelegateToRepository(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 22;
        $repository = Phake::mock(UserRepository::class);
        Phake::when($repository)->findLoginById(22)->thenReturn('nikita');
        Phake::when($repository)->existsByLogin('nikita')->thenReturn(true);
        Phake::when($repository)->existsByEmail('nikita@example.com')->thenReturn(true);
        $service = new UserService($repository);
        // endregion.

        // region Act.
        $login = $service->getLogin();
        $loginExists = $service->isLoginExist('nikita');
        $emailExists = $service->isEmailExist('nikita@example.com');
        // endregion.

        // region Assert.
        $this->assertSame('nikita', $login);
        $this->assertTrue($loginExists);
        $this->assertTrue($emailExists);
        // endregion.
    }

    public function testRegisterValidatesRequiredFields(): void {
        // region Arrange.
        $service = new UserService(Phake::mock(UserRepository::class));
        // endregion.

        // region Act.
        try {
            $service->register('', 'mail@example.com', 'password');
            $this->fail('Expected exception for empty login');
        } catch (ServiceException $e) {
            // region Assert.
            $this->assertSame('Login must not be empty', $e->getMessage());
            // endregion.
        }

        try {
            $service->register('login', '', 'password');
            $this->fail('Expected exception for empty email');
        } catch (ServiceException $e) {
            // region Assert.
            $this->assertSame('Email must not be empty', $e->getMessage());
            // endregion.
        }

        try {
            $service->register('login', 'mail@example.com', '');
            $this->fail('Expected exception for empty password');
        } catch (ServiceException $e) {
            // region Assert.
            $this->assertSame('Password must not be empty', $e->getMessage());
            // endregion.
        }
    }

    public function testRegisterValidatesExistingCredentials(): void {
        // region Arrange.
        $repository = Phake::mock(UserRepository::class);
        Phake::when($repository)->existsByLogin('login')->thenReturn(true);
        Phake::when($repository)->existsByEmail('mail@example.com')->thenReturn(true);
        $service = new UserService($repository);
        // endregion.

        // region Act.
        try {
            $service->register('login', 'mail@example.com', 'password');
            $this->fail('Expected exception for existing login');
        } catch (ServiceException $e) {
            // region Assert.
            $this->assertSame('User with login login already exist', $e->getMessage());
            // endregion.
        }

        Phake::when($repository)->existsByLogin('login')->thenReturn(false);

        try {
            $service->register('login', 'mail@example.com', 'password');
            $this->fail('Expected exception for existing email');
        } catch (ServiceException $e) {
            // region Assert.
            $this->assertSame('User with email mail@example.com already exist', $e->getMessage());
            // endregion.
        }
    }

    public function testRegisterCreatesUserWithHashedPassword(): void {
        // region Arrange.
        $repository = Phake::mock(UserRepository::class);
        Phake::when($repository)->existsByLogin('login')->thenReturn(false);
        Phake::when($repository)->existsByEmail('mail@example.com')->thenReturn(false);
        Phake::when($repository)->create('login', 'mail@example.com', Phake::capture($passwordHash))->thenReturn(15);
        $service = new UserService($repository);
        // endregion.

        // region Act.
        $userId = $service->register('login', 'mail@example.com', 'password');
        // endregion.

        // region Assert.
        $this->assertSame(15, $userId);
        $this->assertTrue(password_verify('password', $passwordHash));
        // endregion.
    }

    public function testLoginReturnsNullForInvalidCredentials(): void {
        // region Arrange.
        $repository = Phake::mock(UserRepository::class);
        Phake::when($repository)->findCredentialsByLoginOrEmail('login')->thenReturn([
                'ID' => 5,
                'PASSWORD' => password_hash('correct-password', PASSWORD_DEFAULT),
        ]);
        $service = new UserService($repository);
        // endregion.

        // region Act.
        $userId = $service->login('login', 'wrong-password');
        // endregion.

        // region Assert.
        $this->assertNull($userId);
        $this->assertArrayNotHasKey('USER_ID', $_SESSION);
        // endregion.
    }

    public function testLoginStoresSessionAndTouchesLastLogin(): void {
        // region Arrange.
        $repository = Phake::mock(UserRepository::class);
        Phake::when($repository)->findCredentialsByLoginOrEmail('login')->thenReturn([
                'ID' => 5,
                'PASSWORD' => password_hash('correct-password', PASSWORD_DEFAULT),
        ]);
        $service = new UserService($repository);
        // endregion.

        // region Act.
        $userId = $service->login('login', 'correct-password');
        // endregion.

        // region Assert.
        $this->assertSame(5, $userId);
        $this->assertSame(5, $_SESSION['USER_ID']);
        Phake::verify($repository)->touchLastLogin(5);
        // endregion.
    }

    public function testLogoutClearsSession(): void {
        // region Arrange.
        $_SESSION['USER_ID'] = 9;
        // endregion.

        // region Act.
        UserService::logout();
        // endregion.

        // region Assert.
        $this->assertArrayNotHasKey('USER_ID', $_SESSION);
        // endregion.
    }
}
