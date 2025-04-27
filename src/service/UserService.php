<?php


namespace app\service;


use app\enum\SubscriptionPlan;
use app\repository\UserRepository;


class UserService {
    function __construct(
            private readonly UserRepository $userRepository
    ) {
    }

    static function getCurrentUserId(): ?int {
        return isset($_SESSION['USER_ID']) ? (int) $_SESSION['USER_ID'] : null;
    }

     function getLogin(): ?string {
        return $this->userRepository->findLoginById((int) $_SESSION['USER_ID']);
    }

    static function getSubscriptionPlan(int $userId): SubscriptionPlan {
        return SubscriptionPlan::Base;
    }

    static function isAuthorized(): bool {
        return self::getCurrentUserId() > 0;
    }

    /**
     * @throws ServiceException
     */
    function register(string $login, string $email, string $password): int {
        if (mb_strlen($login) <= 0) {
            throw new ServiceException('Login must not be empty');
        }

        if (mb_strlen($email) <= 0) {
            throw new ServiceException('Email must not be empty');
        }

        if (mb_strlen($password) <= 0) {
            throw new ServiceException('Password must not be empty');
        }

        if ($this->userRepository->existsByLogin($login)) {
            throw new ServiceException("User with login $login already exist");
        }

        if ($this->userRepository->existsByEmail($email)) {
            throw new ServiceException("User with email $email already exist");
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        return $this->userRepository->create($login, $email, $hash);
    }

    function login(string $login, string $password): ?int {
        $user = $this->userRepository->findCredentialsByLoginOrEmail($login);
        if (!$user || !password_verify($password, $user['PASSWORD'])) {
            return null;
        }

        $userId = (int) $user['ID'];
        $_SESSION['USER_ID'] = $userId;
        $this->userRepository->touchLastLogin($userId);

        return $userId;
    }

    static function logout(): void {
        unset($_SESSION['USER_ID']);
        session_destroy();
    }

    function isLoginExist(string $login): bool {
        return $this->userRepository->existsByLogin($login);
    }

    function isEmailExist(string $email): bool {
        return $this->userRepository->existsByEmail($email);
    }
}
