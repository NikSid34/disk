<?php

declare(strict_types=1);

namespace app\test\repository;

use app\entity\Folder;
use app\repository\FolderRepository;
use app\test\support\BaseTestCase;
use app\test\support\FakePdo;
use app\test\support\FakePdoStatement;

class FolderRepositoryTest extends BaseTestCase {
    public function testCreateAndFindMethodsMapFolderData(): void {
        // region Arrange.
        $pdo = new FakePdo();
        $pdo->setLastInsertId(11);

        $createStatement = FakePdoStatement::create();
        $findByHashStatement = FakePdoStatement::create()->queueFetch($this->folderRow([
                'ID' => 11,
                'HASH' => 'folder-hash',
                'PARENT_ID' => 2,
        ]));
        $findByIdStatement = FakePdoStatement::create()->queueFetch([
                'ID' => 11,
                'HASH' => 'folder-hash',
                'NAME' => 'Folder',
                'PARENT_ID' => 2,
        ]);
        $findPublicStatement = FakePdoStatement::create()->queueFetchAll([
                $this->folderRow([
                        'ID' => 11,
                        'IS_PUBLIC' => 1,
                ]),
        ]);
        $findNameStatement = FakePdoStatement::create()->queueFetchColumn('Folder');
        $findIdWithFiltersStatement = FakePdoStatement::create()->queueFetchColumn('11');
        $findIdWithoutFiltersStatement = FakePdoStatement::create()->queueFetchColumn('12');

        $pdo->expectPrepare('INSERT INTO folder (HASH, USER_ID, PARENT_ID, NAME) VALUES (:hash, :userId, :parentId, :name)', $createStatement);
        $pdo->expectPrepare('SELECT * FROM folder WHERE HASH = :folderHash', $findByHashStatement);
        $pdo->expectPrepare('SELECT ID, HASH, NAME, PARENT_ID FROM folder WHERE ID = :id AND USER_ID = :userId', $findByIdStatement);
        $pdo->expectPrepare('SELECT * FROM folder WHERE IS_PUBLIC = TRUE AND USER_ID = :userId AND IN_TRASH = FALSE', $findPublicStatement);
        $pdo->expectPrepare('SELECT NAME FROM folder WHERE HASH = :hash AND USER_ID = :userId', $findNameStatement);
        $pdo->expectPrepare('SELECT ID FROM folder WHERE HASH = :hash AND USER_ID = :userId AND IN_TRASH = :inTrash', $findIdWithFiltersStatement);
        $pdo->expectPrepare('SELECT ID FROM folder WHERE HASH = :hash', $findIdWithoutFiltersStatement);

        $repository = new FolderRepository($pdo);
        // endregion.

        // region Act.
        $folderId = $repository->create('folder-hash', 5, 2, 'Folder');
        $folder = $repository->findByHash('folder-hash');
        $folderData = $repository->findByIdAndUser(11, 5);
        $publicFolders = $repository->findPublicByUser(5);
        $folderName = $repository->findNameByHashAndUser('folder-hash', 5);
        $filteredId = $repository->findIdByHash('folder-hash', 5, true);
        $plainId = $repository->findIdByHash('another-hash');
        // endregion.

        // region Assert.
        $this->assertSame(11, $folderId);
        $this->assertInstanceOf(Folder::class, $folder);
        $this->assertSame('folder-hash', $folder?->hash);
        $this->assertSame(5, $folder?->userId);
        $this->assertSame(2, $folder?->parentId);
        $this->assertSame('Folder', $folderName);
        $this->assertSame([
                'ID' => 11,
                'HASH' => 'folder-hash',
                'NAME' => 'Folder',
                'PARENT_ID' => 2,
        ], $folderData);
        $this->assertCount(1, $publicFolders);
        $this->assertTrue($publicFolders[0]->isPublic);
        $this->assertSame(11, $filteredId);
        $this->assertSame(12, $plainId);
        $this->assertSame([[
                'hash' => 'folder-hash',
                'userId' => 5,
                'parentId' => 2,
                'name' => 'Folder',
        ]], $createStatement->executedParams);
        // endregion.
    }

    public function testFindUserAndTrashedFoldersRespectFilters(): void {
        // region Arrange.
        $pdo = new FakePdo();
        $userFoldersStatement = FakePdoStatement::create()->queueFetchAll([
                $this->folderRow([
                        'ID' => 1,
                        'HASH' => 'root-folder',
                        'NAME' => 'Documents',
                ]),
        ]);
        $trashedFoldersStatement = FakePdoStatement::create()->queueFetchAll([
                $this->folderRow([
                        'ID' => 2,
                        'HASH' => 'trashed-folder',
                        'NAME' => 'Trash folder',
                        'IN_TRASH' => 1,
                ]),
        ]);

        $pdo->expectPrepare(
                '
            SELECT 
                f.*,
                COALESCE(SUM(fo.FILE_SIZE), 0) AS total_size
            FROM folder f
            LEFT JOIN file fi ON fi.FOLDER_ID = f.ID AND fi.USER_ID = f.USER_ID AND fi.IN_TRASH = FALSE
            LEFT JOIN file_object fo ON fo.ID = fi.FILE_OBJECT_ID
            WHERE f.IN_TRASH = FALSE
              AND f.USER_ID = :userId
         AND f.PARENT_ID IS NULL AND f.NAME LIKE :search GROUP BY f.ID ORDER BY NAME ASC',
                $userFoldersStatement
        );
        $pdo->expectPrepare(
                '
            SELECT 
                f.*,
                COALESCE(SUM(fo.FILE_SIZE), 0) AS total_size
            FROM folder f
            LEFT JOIN file fi ON fi.FOLDER_ID = f.ID AND fi.USER_ID = f.USER_ID AND fi.IN_TRASH = TRUE
            LEFT JOIN file_object fo ON fo.ID = fi.FILE_OBJECT_ID
            WHERE f.USER_ID = :userId
              AND f.IN_TRASH = TRUE
         AND f.PARENT_ID = :parentId GROUP BY f.ID ORDER BY total_size DESC',
                $trashedFoldersStatement
        );

        $repository = new FolderRepository($pdo);
        // endregion.

        // region Act.
        $userFolders = $repository->findUserFolders(5, null, 'NAME', 'ASC', 'Doc');
        $trashedFolders = $repository->findTrashedFolders(5, 2, 'total_size', 'DESC');
        // endregion.

        // region Assert.
        $this->assertCount(1, $userFolders);
        $this->assertSame('Documents', $userFolders[0]->name);
        $this->assertCount(1, $trashedFolders);
        $this->assertSame('trashed-folder', $trashedFolders[0]->hash);
        $this->assertSame([[
                'userId' => 5,
                'search' => '%Doc%',
        ]], $userFoldersStatement->executedParams);
        $this->assertSame([[
                'userId' => 5,
                'parentId' => 2,
        ]], $trashedFoldersStatement->executedParams);
        // endregion.
    }

    public function testTrashAndMutationMethodsExecuteExpectedQueries(): void {
        // region Arrange.
        $pdo = new FakePdo();
        $markInTrashWithUserStatement = FakePdoStatement::create()->setRowCount(1);
        $markInTrashWithoutUserStatement = FakePdoStatement::create()->setRowCount(2);
        $restoreStatement = FakePdoStatement::create()->setRowCount(1);
        $addToTrashFirstStatement = FakePdoStatement::create();
        $addToTrashSecondStatement = FakePdoStatement::create();
        $removeFromTrashFirstStatement = FakePdoStatement::create();
        $removeFromTrashSecondStatement = FakePdoStatement::create();
        $markByIdsStatement = FakePdoStatement::create()->setRowCount(2);
        $restoreByIdsStatement = FakePdoStatement::create()->setRowCount(2);
        $findChildIdsStatement = FakePdoStatement::create()->queueFetchAll([21, 22]);
        $deleteByIdStatement = FakePdoStatement::create();
        $markPublicStatement = FakePdoStatement::create();
        $incrementPublicCounterStatement = FakePdoStatement::create();

        $pdo->expectPrepare('UPDATE folder SET IN_TRASH = TRUE WHERE HASH = :hash AND IN_TRASH = FALSE AND USER_ID = :userId', $markInTrashWithUserStatement);
        $pdo->expectPrepare('UPDATE folder SET IN_TRASH = TRUE WHERE HASH = :hash AND IN_TRASH = FALSE', $markInTrashWithoutUserStatement);
        $pdo->expectPrepare('UPDATE folder SET IN_TRASH = FALSE WHERE USER_ID = :uid AND HASH = :hash AND IN_TRASH = TRUE', $restoreStatement);
        $pdo->expectPrepare(
                '
            INSERT INTO trash (FOLDER_ID)
            SELECT :folderId
            WHERE NOT EXISTS (
                SELECT 1
                FROM trash
                WHERE FOLDER_ID = :folderId
            )
        ',
                $addToTrashFirstStatement
        );
        $pdo->expectPrepare(
                '
            INSERT INTO trash (FOLDER_ID)
            SELECT :folderId
            WHERE NOT EXISTS (
                SELECT 1
                FROM trash
                WHERE FOLDER_ID = :folderId
            )
        ',
                $addToTrashSecondStatement
        );
        $pdo->expectPrepare('DELETE FROM trash WHERE FOLDER_ID = ?', $removeFromTrashFirstStatement);
        $pdo->expectPrepare('DELETE FROM trash WHERE FOLDER_ID = ?', $removeFromTrashSecondStatement);
        $pdo->expectPrepare('UPDATE folder SET IN_TRASH = TRUE WHERE ID IN (:folder_0, :folder_1)', $markByIdsStatement);
        $pdo->expectPrepare('UPDATE folder SET IN_TRASH = FALSE WHERE ID IN (:folder_0, :folder_1)', $restoreByIdsStatement);
        $pdo->expectPrepare('SELECT ID FROM folder WHERE PARENT_ID = :parentId', $findChildIdsStatement);
        $pdo->expectPrepare('DELETE FROM folder WHERE ID = ?', $deleteByIdStatement);
        $pdo->expectPrepare('UPDATE folder SET IS_PUBLIC = TRUE WHERE ID = ?', $markPublicStatement);
        $pdo->expectPrepare('UPDATE folder SET PUBLIC_VISITS_NUM = PUBLIC_VISITS_NUM + 1, LAST_VISITED_DATE = CURRENT_TIMESTAMP WHERE ID = ?', $incrementPublicCounterStatement);

        $repository = new FolderRepository($pdo);
        // endregion.

        // region Act.
        $markedWithUser = $repository->markInTrash('folder-hash', 5);
        $markedWithoutUser = $repository->markInTrash('folder-hash');
        $restored = $repository->restoreFromTrash(5, 'folder-hash');
        $repository->addToTrash(21);
        $repository->addToTrashMany([22]);
        $repository->removeFromTrash(21);
        $repository->removeFromTrashMany([22]);
        $markedByIds = $repository->markInTrashByIds([21, 22]);
        $restoredByIds = $repository->restoreFromTrashByIds([21, 22]);
        $emptyMarked = $repository->markInTrashByIds([]);
        $emptyRestored = $repository->restoreFromTrashByIds([]);
        $childIds = $repository->findChildFolderIds(10);
        $repository->deleteById(99);
        $repository->markPublic(77);
        $repository->incrementPublicCounter(77);
        // endregion.

        // region Assert.
        $this->assertSame(1, $markedWithUser);
        $this->assertSame(2, $markedWithoutUser);
        $this->assertSame(1, $restored);
        $this->assertSame(2, $markedByIds);
        $this->assertSame(2, $restoredByIds);
        $this->assertSame(0, $emptyMarked);
        $this->assertSame(0, $emptyRestored);
        $this->assertSame([21, 22], $childIds);
        $this->assertSame([['hash' => 'folder-hash', 'userId' => 5]], $markInTrashWithUserStatement->executedParams);
        $this->assertSame([['hash' => 'folder-hash']], $markInTrashWithoutUserStatement->executedParams);
        $this->assertSame([['uid' => 5, 'hash' => 'folder-hash']], $restoreStatement->executedParams);
        $this->assertSame([['folderId' => 21]], $addToTrashFirstStatement->executedParams);
        $this->assertSame([['folderId' => 22]], $addToTrashSecondStatement->executedParams);
        $this->assertSame([[21]], $removeFromTrashFirstStatement->executedParams);
        $this->assertSame([[22]], $removeFromTrashSecondStatement->executedParams);
        $this->assertSame([['folder_0' => 21, 'folder_1' => 22]], $markByIdsStatement->executedParams);
        $this->assertSame([['folder_0' => 21, 'folder_1' => 22]], $restoreByIdsStatement->executedParams);
        $this->assertSame([['parentId' => 10]], $findChildIdsStatement->executedParams);
        $this->assertSame([[99]], $deleteByIdStatement->executedParams);
        $this->assertSame([[77]], $markPublicStatement->executedParams);
        $this->assertSame([[77]], $incrementPublicCounterStatement->executedParams);
        // endregion.
    }

    private function folderRow(array $overrides = []): array {
        return array_merge([
                'ID' => 1,
                'HASH' => 'folder-hash',
                'USER_ID' => 5,
                'PARENT_ID' => null,
                'NAME' => 'Folder',
                'CREATED_AT' => '2025-01-01 10:00:00',
                'PUBLIC_VISITS_NUM' => 3,
                'LAST_VISITED_DATE' => '2025-01-02 10:00:00',
                'IS_PUBLIC' => 0,
        ], $overrides);
    }
}
