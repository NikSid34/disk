<?php

declare(strict_types=1);

namespace app\test\controller;

use app\container\AppContainer;
use app\controller\UserController;
use app\repository\UserRepository;
use app\service\ServiceException;
use app\service\UserService;
use app\test\support\BaseTestCase;
use app\test\support\FakePdo;
use Phake;

class UserControllerTest extends BaseTestCase {
    public function testInstanceReturnsController(): void {
        // region Arrange.
        $container = new AppContainer('config_example.json');
        $this->setPrivateProperty($container, 'pdo', new FakePdo());
        $this->setPrivateProperty(AppContainer::class, 'default', $container);
        // endregion.

        // region Act.
        $controller = UserController::instance();
        // endregion.

        // region Assert.
        $this->assertInstanceOf(UserController::class, $controller);
        // endregion.
    }

    public function testLoginRendersPublicPage(): void {
        // region Arrange.
        $controller = new UserController(new UserService(Phake::mock(UserRepository::class)));
        $request = $this->createRequest('GET', '/user/login', ['invalid' => 1, 'registration' => 1]);
        $response = $this->createResponse();
        // endregion.

        // region Act.
        $result = $controller->login($request, $response);
        // endregion.

        // region Assert.
        $this->assertSame(200, $result->getStatusCode());
        $this->assertNotSame('', $this->getResponseBody($result));
        // endregion.
    }

    public function testAuthorizeRedirectsDependingOnLoginResult(): void {
        // region Arrange.
        $userService = Phake::mock(UserService::class);
        Phake::when($userService)->login('user', 'bad')->thenReturn(null);
        Phake::when($userService)->login('user', 'good')->thenReturn(5);
        $controller = new UserController($userService);
        // endregion.

        // region Act.
        $invalidResponse = $controller->authorize(
                $this->createRequest('POST', '/user/authorize', [], ['login' => 'user', 'password' => 'bad']),
                $this->createResponse()
        );
        $validResponse = $controller->authorize(
                $this->createRequest('POST', '/user/authorize', [], ['login' => 'user', 'password' => 'good']),
                $this->createResponse()
        );
        // endregion.

        // region Assert.
        $this->assertSame(302, $invalidResponse->getStatusCode());
        $this->assertSame(['/user/login?invalid=1'], $invalidResponse->getHeader('Location'));
        $this->assertSame(302, $validResponse->getStatusCode());
        $this->assertSame(['/disk/'], $validResponse->getHeader('Location'));
        // endregion.
    }

    public function testRegisterHandlesValidationAndSuccess(): void {
        // region Arrange.
        $userService = Phake::mock(UserService::class);
        Phake::when($userService)->isLoginExist('taken')->thenReturn(true);
        Phake::when($userService)->isLoginExist('free')->thenReturn(false);
        Phake::when($userService)->isEmailExist('taken@example.com')->thenReturn(true);
        Phake::when($userService)->register('free', 'free@example.com', 'secret')->thenReturn(5);
        $controller = new UserController($userService);
        // endregion.

        // region Act.
        $passwordMismatch = $controller->register(
                $this->createRequest('POST', '/user/register', [], [
                        'login' => 'user',
                        'email' => 'mail@example.com',
                        'password' => 'a',
                        'password_confirm' => 'b',
                ]),
                $this->createResponse()
        );
        $existingLogin = $controller->register(
                $this->createRequest('POST', '/user/register', [], [
                        'login' => 'taken',
                        'email' => 'mail@example.com',
                        'password' => 'secret',
                        'password_confirm' => 'secret',
                ]),
                $this->createResponse()
        );
        $existingEmail = $controller->register(
                $this->createRequest('POST', '/user/register', [], [
                        'login' => 'free',
                        'email' => 'taken@example.com',
                        'password' => 'secret',
                        'password_confirm' => 'secret',
                ]),
                $this->createResponse()
        );
        $success = $controller->register(
                $this->createRequest('POST', '/user/register', [], [
                        'login' => 'free',
                        'email' => 'free@example.com',
                        'password' => 'secret',
                        'password_confirm' => 'secret',
                ]),
                $this->createResponse()
        );
        // endregion.

        // region Assert.
        $this->assertSame(['/user/login?registration=1&unmatchedPassword=1'], $passwordMismatch->getHeader('Location'));
        $this->assertSame(['/user/login?registration=1&loginAlreadyExists=1'], $existingLogin->getHeader('Location'));
        $this->assertSame(['/user/login?registration=1&emailAlreadyExists=1'], $existingEmail->getHeader('Location'));
        $this->assertSame(['/disk/'], $success->getHeader('Location'));
        // endregion.
    }

    public function testRegisterRendersErrorPageAndLogoutRedirects(): void {
        // region Arrange.
        $userService = Phake::mock(UserService::class);
        Phake::when($userService)->isLoginExist('free')->thenReturn(false);
        Phake::when($userService)->isEmailExist('free@example.com')->thenReturn(false);
        Phake::when($userService)->register('free', 'free@example.com', 'secret')->thenThrow(new ServiceException('boom'));
        $controller = new UserController($userService);
        $logoutController = new UserController(new UserService(Phake::mock(UserRepository::class)));
        // endregion.

        // region Act.
        $registerResponse = $controller->register(
                $this->createRequest('POST', '/user/register', [], [
                        'login' => 'free',
                        'email' => 'free@example.com',
                        'password' => 'secret',
                        'password_confirm' => 'secret',
                ]),
                $this->createResponse()
        );
        $_SESSION['USER_ID'] = 5;
        $logoutResponse = $logoutController->logout($this->createRequest('POST', '/user/logout'), $this->createResponse());
        // endregion.

        // region Assert.
        $this->assertSame(200, $registerResponse->getStatusCode());
        $this->assertNotSame('', $this->getResponseBody($registerResponse));
        $this->assertSame(['/disk/'], $logoutResponse->getHeader('Location'));
        $this->assertArrayNotHasKey('USER_ID', $_SESSION);
        // endregion.
    }
}
