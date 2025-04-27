<?php


namespace app\repository;


use PDO;


class UserRepository {
    public function __construct(
            private readonly PDO $pdo
    ) {
    }

    public function findLoginById(int $userId): ?string {
        $stmt = $this->pdo->prepare('SELECT LOGIN FROM user WHERE ID = ?');
        $stmt->execute(array($userId));

        $login = $stmt->fetchColumn();

        return $login !== false ? (string) $login : null;
    }

    public function existsByLogin(string $login): bool {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user WHERE LOGIN = ?');
        $stmt->execute(array($login));

        return (int) $stmt->fetchColumn() > 0;
    }

    public function existsByEmail(string $email): bool {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM user WHERE EMAIL = ?');
        $stmt->execute(array($email));

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(string $login, string $email, string $passwordHash): int {
        $stmt = $this->pdo->prepare('INSERT INTO user (LOGIN, EMAIL, PASSWORD) VALUES (?, ?, ?)');
        $stmt->execute(array($login, $email, $passwordHash));

        return (int) $this->pdo->lastInsertId();
    }

    public function findCredentialsByLoginOrEmail(string $loginOrEmail): ?array {
        $stmt = $this->pdo->prepare('SELECT ID, PASSWORD FROM user WHERE LOGIN = ? OR EMAIL = ?');
        $stmt->execute(array($loginOrEmail, $loginOrEmail));

        $user = $stmt->fetch();

        return $user !== false ? $user : null;
    }

    public function touchLastLogin(int $userId): void {
        $stmt = $this->pdo->prepare('UPDATE user SET LAST_LOGIN = CURRENT_TIMESTAMP WHERE ID = ?');
        $stmt->execute(array($userId));
    }
}
