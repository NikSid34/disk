<?php


namespace app\controller;


use app\container\AppContainer;
use app\service\UserService;
use app\ui\Component;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


class UserController {
    function __construct(
            private readonly UserService $userService
    ) {
    }

    static function instance(): self {
        return AppContainer::fromDefaultConfig()->userController();
    }

    function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $queryParams = $request->getQueryParams();
        $invalidLogin = isset($queryParams['invalid']);
        $registration = isset($queryParams['registration']);
        $unmatchedPassword = isset($queryParams['unmatchedPassword']);
        $loginAlreadyExists = isset($queryParams['loginAlreadyExists']);
        $emailAlreadyExists = isset($queryParams['emailAlreadyExists']);

        try {
            $html = Component::render('simpledisk:layout.public', [
                    'title' => 'Вход | SimpleDisk',
                    'pageComponent' => 'simpledisk:login.page',
                    'pageParams' => [
                            'invalidLogin' => $invalidLogin,
                            'registration' => $registration,
                            'unmatchedPassword' => $unmatchedPassword,
                            'loginAlreadyExists' => $loginAlreadyExists,
                            'emailAlreadyExists' => $emailAlreadyExists,
                    ],
            ]);
        } catch (Exception) {
            $html = Component::render('simpledisk:layout.public', [
                    'title' => 'Ошибка | SimpleDisk',
                    'pageComponent' => 'simpledisk:error.page',
                    'pageParams' => [
                            'message' => 'Не удалось открыть страницу входа.',
                    ],
            ]);
        }

        $response->getBody()->write($html);

        return $response;
    }

    function authorize(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $request->getParsedBody();
        $login = $data['login'] ?? '';
        $password = $data['password'] ?? '';

        $userId = $this->userService->login($login, $password);

        if ($userId === null) {
            return $response
                    ->withHeader('Location', '/user/login?invalid=1')
                    ->withStatus(302);
        }

        return $response
                ->withHeader('Location', '/disk/')
                ->withStatus(302);
    }

    function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $data = $request->getParsedBody();
        $login = trim($data['login'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $passwordConfirm = $data['password_confirm'] ?? '';

        if ($password !== $passwordConfirm) {
            return $response
                    ->withHeader('Location', '/user/login?registration=1&unmatchedPassword=1')
                    ->withStatus(302);
        }

        if ($this->userService->isLoginExist($login)) {
            return $response
                    ->withHeader('Location', '/user/login?registration=1&loginAlreadyExists=1')
                    ->withStatus(302);
        }

        if ($this->userService->isEmailExist($email)) {
            return $response
                    ->withHeader('Location', '/user/login?registration=1&emailAlreadyExists=1')
                    ->withStatus(302);
        }

        try {
            $this->userService->register($login, $email, $password);
        } catch (Exception) {
            $html = Component::render('simpledisk:layout.public', [
                    'title' => 'Ошибка | SimpleDisk',
                    'pageComponent' => 'simpledisk:error.page',
                    'pageParams' => [
                            'message' => 'Не удалось завершить регистрацию.',
                    ],
            ]);
            $response->getBody()->write($html);

            return $response;
        }

        return $response
                ->withHeader('Location', '/disk/')
                ->withStatus(302);
    }

    function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
        $this->userService->logout();

        return $response
                ->withHeader('Location', '/disk/')
                ->withStatus(302);
    }
}
