<?php

declare(strict_types=1);

namespace app\test\repository;

use app\repository\UserRepository;
use app\test\support\BaseTestCase;
use app\test\support\FakePdo;
use app\test\support\FakePdoStatement;

class UserRepositoryTest extends BaseTestCase {
    public function testFindLoginByIdReturnsLoginOrNull(): void {
        // region Arrange.
        $pdo = new FakePdo();
        $statement = FakePdoStatement::create()->queueFetchColumn('nikita');
        $missingStatement = FakePdoStatement::create()->queueFetchColumn(false);
        $pdo->expectPrepare('SELECT LOGIN FROM user WHERE ID = ?', $statement);
        $pdo->expectPrepare('SELECT LOGIN FROM user WHERE ID = ?', $missingStatement);
        $repository = new UserRepository($pdo);
        // endregion.

        // region Act.
        $login = $repository->findLoginById(5);
        $missingLogin = $repository->findLoginById(6);
        // endregion.

        // region Assert.
        $this->assertSame('nikita', $login);
        $this->assertNull($missingLogin);
        $this->assertSame([[5]], $statement->executedParams);
        $this->assertSame([[6]], $missingStatement->executedParams);
        // endregion.
    }

    public function testExistsChecksUseCountQueries(): void {
        // region Arrange.
        $pdo = new FakePdo();
        $loginStatement = FakePdoStatement::create()->queueFetchColumn('1');
        $emailStatement = FakePdoStatement::create()->queueFetchColumn('0');
        $pdo->expectPrepare('SELECT COUNT(*) FROM user WHERE LOGIN = ?', $loginStatement);
        $pdo->expectPrepare('SELECT COUNT(*) FROM user WHERE EMAIL = ?', $emailStatement);
        $repository = new UserRepository($pdo);
        // endregion.

        // region Act.
        $loginExists = $repository->existsByLogin('nikita');
        $emailExists = $repository->existsByEmail('missing@example.com');
        // endregion.

        // region Assert.
        $this->assertTrue($loginExists);
        $this->assertFalse($emailExists);
        $this->assertSame([['nikita']], $loginStatement->executedParams);
        $this->assertSame([['missing@example.com']], $emailStatement->executedParams);
        // endregion.
    }

    public function testCreateReturnsLastInsertId(): void {
        // region Arrange.
        $pdo = new FakePdo();
        $pdo->setLastInsertId(17);
        $statement = FakePdoStatement::create();
        $pdo->expectPrepare('INSERT INTO user (LOGIN, EMAIL, PASSWORD) VALUES (?, ?, ?)', $statement);
        $repository = new UserRepository($pdo);
        // endregion.

        // region Act.
        $userId = $repository->create('tester', 'tester@example.com', 'password-hash');
        // endregion.

        // region Assert.
        $this->assertSame(17, $userId);
        $this->assertSame([['tester', 'tester@example.com', 'password-hash']], $statement->executedParams);
        // endregion.
    }

    public function testFindCredentialsByLoginOrEmailReturnsRowOrNull(): void {
        // region Arrange.
        $pdo = new FakePdo();
        $statement = FakePdoStatement::create()->queueFetch([
                'ID' => 21,
                'PASSWORD' => 'hash',
        ]);
        $missingStatement = FakePdoStatement::create()->queueFetch(false);
        $pdo->expectPrepare('SELECT ID, PASSWORD FROM user WHERE LOGIN = ? OR EMAIL = ?', $statement);
        $pdo->expectPrepare('SELECT ID, PASSWORD FROM user WHERE LOGIN = ? OR EMAIL = ?', $missingStatement);
        $repository = new UserRepository($pdo);
        // endregion.

        // region Act.
        $credentials = $repository->findCredentialsByLoginOrEmail('nikita');
        $missingCredentials = $repository->findCredentialsByLoginOrEmail('missing');
        // endregion.

        // region Assert.
        $this->assertSame(['ID' => 21, 'PASSWORD' => 'hash'], $credentials);
        $this->assertNull($missingCredentials);
        $this->assertSame([['nikita', 'nikita']], $statement->executedParams);
        $this->assertSame([['missing', 'missing']], $missingStatement->executedParams);
        // endregion.
    }

    public function testTouchLastLoginExecutesUpdate(): void {
        // region Arrange.
        $pdo = new FakePdo();
        $statement = FakePdoStatement::create();
        $pdo->expectPrepare('UPDATE user SET LAST_LOGIN = CURRENT_TIMESTAMP WHERE ID = ?', $statement);
        $repository = new UserRepository($pdo);
        // endregion.

        // region Act.
        $repository->touchLastLogin(31);
        // endregion.

        // region Assert.
        $this->assertSame([[31]], $statement->executedParams);
        // endregion.
    }
}
