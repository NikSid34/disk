<?php

declare(strict_types=1);

namespace app\test\support;

use app\entity\File;
use app\entity\FileObject;
use app\entity\Folder;
use app\entity\User;
use app\enum\FileIndexStatus;
use app\enum\FileType;
use DateTime;

class TestEntityFactory {
    public static function createFileObject(array $overrides = []): FileObject {
        $data = array_merge([
                'id' => 10,
                'hash' => 'file-object-hash',
                'storagePath' => sys_get_temp_dir() . '/simpledisk-file-object.txt',
                'contentType' => 'text/plain',
                'fileSize' => 256,
                'refCount' => 1,
                'createdAt' => new DateTime('2025-01-01 10:00:00'),
        ], $overrides);

        return new FileObject(
                $data['id'],
                $data['hash'],
                $data['storagePath'],
                $data['contentType'],
                $data['fileSize'],
                $data['refCount'],
                $data['createdAt']
        );
    }

    public static function createFile(array $overrides = []): File {
        $fileObject = $overrides['fileObject'] ?? self::createFileObject();
        $thumbnail = $overrides['thumbnail'] ?? null;
        $data = array_merge([
                'id' => 15,
                'hash' => 'file-hash',
                'userId' => 5,
                'folderId' => 7,
                'name' => 'document.txt',
                'extension' => 'txt',
                'fileType' => FileType::Text,
                'createdAt' => new DateTime('2025-01-02 10:00:00'),
                'visitsNum' => 2,
                'isPublic' => false,
                'searchIndexStatus' => FileIndexStatus::Pending,
                'lastVisited' => new DateTime('2025-01-03 10:00:00'),
        ], array_diff_key($overrides, ['fileObject' => true, 'thumbnail' => true]));

        return new File(
                $data['id'],
                $data['hash'],
                $data['userId'],
                $data['folderId'],
                $data['name'],
                $data['extension'],
                $data['fileType'],
                $data['createdAt'],
                $fileObject,
                $thumbnail,
                $data['visitsNum'],
                $data['isPublic'],
                $data['searchIndexStatus'],
                $data['lastVisited']
        );
    }

    public static function createFolder(array $overrides = []): Folder {
        $data = array_merge([
                'id' => 20,
                'hash' => 'folder-hash',
                'userId' => 5,
                'parentId' => null,
                'name' => 'Folder',
                'createdAt' => new DateTime('2025-01-03 10:00:00'),
                'visitsNum' => 1,
                'lastVisited' => new DateTime('2025-01-04 10:00:00'),
                'isPublic' => false,
        ], $overrides);

        return new Folder(
                $data['id'],
                $data['hash'],
                $data['userId'],
                $data['parentId'],
                $data['name'],
                $data['createdAt'],
                $data['visitsNum'],
                $data['lastVisited'],
                $data['isPublic']
        );
    }

    public static function createUser(array $overrides = []): User {
        $data = array_merge([
                'id' => 1,
                'login' => 'tester',
                'email' => 'tester@example.com',
                'password' => 'password-hash',
                'lastLogin' => new DateTime('2025-01-05 10:00:00'),
                'registerDate' => new DateTime('2025-01-01 10:00:00'),
        ], $overrides);

        return new User(
                $data['id'],
                $data['login'],
                $data['email'],
                $data['password'],
                $data['lastLogin'],
                $data['registerDate']
        );
    }
}
