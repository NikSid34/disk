<?php

declare(strict_types=1);

namespace app\test\support;

use PDO;

abstract class DatabaseTestCase extends BaseTestCase {
    protected PDO $pdo;

    protected function setUp(): void {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->createSchema();
    }

    protected function createSchema(): void {
        $this->pdo->exec(
                'CREATE TABLE user (
                    ID INTEGER PRIMARY KEY AUTOINCREMENT,
                    LOGIN TEXT NOT NULL UNIQUE,
                    EMAIL TEXT NOT NULL UNIQUE,
                    PASSWORD TEXT NOT NULL,
                    LAST_LOGIN TEXT NULL,
                    DATE_REGISTER TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )'
        );

        $this->pdo->exec(
                'CREATE TABLE folder (
                    ID INTEGER PRIMARY KEY AUTOINCREMENT,
                    HASH TEXT NOT NULL UNIQUE,
                    USER_ID INTEGER NOT NULL,
                    PARENT_ID INTEGER NULL,
                    NAME TEXT NOT NULL,
                    CREATED_AT TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    IN_TRASH INTEGER NOT NULL DEFAULT 0,
                    IS_PUBLIC INTEGER NOT NULL DEFAULT 0,
                    PUBLIC_VISITS_NUM INTEGER NOT NULL DEFAULT 0,
                    LAST_VISITED_DATE TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (USER_ID) REFERENCES user(ID) ON DELETE CASCADE,
                    FOREIGN KEY (PARENT_ID) REFERENCES folder(ID) ON DELETE CASCADE
                )'
        );

        $this->pdo->exec(
                'CREATE TABLE file_object (
                    ID INTEGER PRIMARY KEY AUTOINCREMENT,
                    HASH TEXT NOT NULL UNIQUE,
                    STORAGE_PATH TEXT NOT NULL,
                    CONTENT_TYPE TEXT NOT NULL,
                    FILE_SIZE INTEGER NOT NULL,
                    REF_COUNT INTEGER NOT NULL DEFAULT 1,
                    CREATED_AT TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )'
        );

        $this->pdo->exec(
                'CREATE TABLE file (
                    ID INTEGER PRIMARY KEY AUTOINCREMENT,
                    HASH TEXT NOT NULL UNIQUE,
                    USER_ID INTEGER NOT NULL,
                    FOLDER_ID INTEGER NULL,
                    FILE_OBJECT_ID INTEGER NOT NULL,
                    NAME TEXT NOT NULL,
                    CREATED_AT TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    SEARCH_INDEX_STATUS INTEGER NOT NULL DEFAULT 0,
                    IN_TRASH INTEGER NOT NULL DEFAULT 0,
                    IS_PUBLIC INTEGER NOT NULL DEFAULT 0,
                    PUBLIC_VISITS_NUM INTEGER NOT NULL DEFAULT 0,
                    LAST_VISITED_DATE TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (USER_ID) REFERENCES user(ID) ON DELETE CASCADE,
                    FOREIGN KEY (FOLDER_ID) REFERENCES folder(ID) ON DELETE CASCADE,
                    FOREIGN KEY (FILE_OBJECT_ID) REFERENCES file_object(ID) ON DELETE CASCADE
                )'
        );

        $this->pdo->exec(
                'CREATE TABLE thumbnail (
                    ID INTEGER PRIMARY KEY AUTOINCREMENT,
                    QUALITY_LEVEL INTEGER NOT NULL,
                    FILE_OBJECT_ID INTEGER NOT NULL,
                    FILE_ID INTEGER NOT NULL,
                    FOREIGN KEY (FILE_OBJECT_ID) REFERENCES file_object(ID) ON DELETE CASCADE,
                    FOREIGN KEY (FILE_ID) REFERENCES file(ID) ON DELETE CASCADE
                )'
        );

        $this->pdo->exec(
                'CREATE TABLE trash (
                    ID INTEGER PRIMARY KEY AUTOINCREMENT,
                    FILE_ID INTEGER NULL,
                    FOLDER_ID INTEGER NULL,
                    CREATED_AT TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (FILE_ID) REFERENCES file(ID) ON DELETE CASCADE,
                    FOREIGN KEY (FOLDER_ID) REFERENCES folder(ID) ON DELETE CASCADE
                )'
        );
    }

    protected function insertUser(
            string $login = 'user',
            string $email = 'user@example.com',
            string $password = 'hash',
            ?string $lastLogin = '2025-01-01 00:00:00'
    ): int {
        $stmt = $this->pdo->prepare(
                'INSERT INTO user (LOGIN, EMAIL, PASSWORD, LAST_LOGIN) VALUES (:login, :email, :password, :lastLogin)'
        );
        $stmt->execute([
                'login' => $login,
                'email' => $email,
                'password' => $password,
                'lastLogin' => $lastLogin,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function insertFolder(
            string $hash,
            int $userId,
            ?int $parentId,
            string $name,
            bool $inTrash = false,
            bool $isPublic = false,
            int $visits = 0,
            string $createdAt = '2025-01-01 10:00:00',
            string $lastVisitedDate = '2025-01-02 10:00:00'
    ): int {
        $stmt = $this->pdo->prepare(
                'INSERT INTO folder
                    (HASH, USER_ID, PARENT_ID, NAME, CREATED_AT, IN_TRASH, IS_PUBLIC, PUBLIC_VISITS_NUM, LAST_VISITED_DATE)
                 VALUES
                    (:hash, :userId, :parentId, :name, :createdAt, :inTrash, :isPublic, :visits, :lastVisitedDate)'
        );
        $stmt->execute([
                'hash' => $hash,
                'userId' => $userId,
                'parentId' => $parentId,
                'name' => $name,
                'createdAt' => $createdAt,
                'inTrash' => $inTrash ? 1 : 0,
                'isPublic' => $isPublic ? 1 : 0,
                'visits' => $visits,
                'lastVisitedDate' => $lastVisitedDate,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function insertFileObject(
            string $hash,
            string $storagePath = '/tmp/file.bin',
            string $contentType = 'application/octet-stream',
            int $fileSize = 100,
            int $refCount = 1,
            string $createdAt = '2025-01-01 09:00:00'
    ): int {
        $stmt = $this->pdo->prepare(
                'INSERT INTO file_object (HASH, STORAGE_PATH, CONTENT_TYPE, FILE_SIZE, REF_COUNT, CREATED_AT)
                 VALUES (:hash, :storagePath, :contentType, :fileSize, :refCount, :createdAt)'
        );
        $stmt->execute([
                'hash' => $hash,
                'storagePath' => $storagePath,
                'contentType' => $contentType,
                'fileSize' => $fileSize,
                'refCount' => $refCount,
                'createdAt' => $createdAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function insertFile(
            string $hash,
            int $userId,
            ?int $folderId,
            int $fileObjectId,
            string $name,
            bool $inTrash = false,
            bool $isPublic = false,
            int $visits = 0,
            string $createdAt = '2025-01-01 11:00:00',
            int $searchIndexStatus = 0,
            string $lastVisitedDate = '2025-01-02 11:00:00'
    ): int {
        $stmt = $this->pdo->prepare(
                'INSERT INTO file
                    (HASH, USER_ID, FOLDER_ID, FILE_OBJECT_ID, NAME, CREATED_AT, SEARCH_INDEX_STATUS, IN_TRASH, IS_PUBLIC, PUBLIC_VISITS_NUM, LAST_VISITED_DATE)
                 VALUES
                    (:hash, :userId, :folderId, :fileObjectId, :name, :createdAt, :searchIndexStatus, :inTrash, :isPublic, :visits, :lastVisitedDate)'
        );
        $stmt->execute([
                'hash' => $hash,
                'userId' => $userId,
                'folderId' => $folderId,
                'fileObjectId' => $fileObjectId,
                'name' => $name,
                'createdAt' => $createdAt,
                'searchIndexStatus' => $searchIndexStatus,
                'inTrash' => $inTrash ? 1 : 0,
                'isPublic' => $isPublic ? 1 : 0,
                'visits' => $visits,
                'lastVisitedDate' => $lastVisitedDate,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function insertTrash(?int $fileId = null, ?int $folderId = null): void {
        $stmt = $this->pdo->prepare('INSERT INTO trash (FILE_ID, FOLDER_ID) VALUES (:fileId, :folderId)');
        $stmt->execute([
                'fileId' => $fileId,
                'folderId' => $folderId,
        ]);
    }
}
