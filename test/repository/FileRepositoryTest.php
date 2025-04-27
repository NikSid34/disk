<?php

declare(strict_types=1);

namespace app\test\repository;

use app\entity\File;
use app\entity\FileObject;
use app\enum\FileIndexStatus;
use app\enum\FileType;
use app\repository\FileRepository;
use app\test\support\BaseTestCase;
use app\test\support\FakePdo;
use app\test\support\FakePdoStatement;

class FileRepositoryTest extends BaseTestCase {
    public function testFileObjectLifecycleMethods(): void {
        // region Arrange.
        $pdo = new FakePdo();
        $pdo->setLastInsertId(31);

        $findByHashStatement = FakePdoStatement::create()->queueFetch($this->fileObjectRow([
                'ID' => 31,
                'HASH' => 'object-hash',
        ]));
        $findByIdAfterCreateStatement = FakePdoStatement::create()->queueFetch($this->fileObjectRow([
                'ID' => 31,
                'HASH' => 'created-hash',
        ]));
        $findByIdStatement = FakePdoStatement::create()->queueFetch($this->fileObjectRow([
                'ID' => 32,
                'HASH' => 'object-id-hash',
        ]));
        $createStatement = FakePdoStatement::create();
        $incrementStatement = FakePdoStatement::create();
        $decrementStatement = FakePdoStatement::create();
        $deleteStatement = FakePdoStatement::create();

        $pdo->expectPrepare('SELECT * FROM file_object WHERE HASH = :hash', $findByHashStatement);
        $pdo->expectPrepare('INSERT INTO file_object (HASH, STORAGE_PATH, CONTENT_TYPE, FILE_SIZE) VALUES (?, ?, ?, ?)', $createStatement);
        $pdo->expectPrepare('SELECT * FROM file_object WHERE ID = :id', $findByIdAfterCreateStatement);
        $pdo->expectPrepare('SELECT * FROM file_object WHERE ID = :id', $findByIdStatement);
        $pdo->expectPrepare('UPDATE file_object SET REF_COUNT = REF_COUNT + 1 WHERE ID = ?', $incrementStatement);
        $pdo->expectPrepare('UPDATE file_object SET REF_COUNT = REF_COUNT - 1 WHERE ID = ?', $decrementStatement);
        $pdo->expectPrepare('DELETE FROM file_object WHERE ID = ?', $deleteStatement);

        $repository = new FileRepository($pdo);
        // endregion.

        // region Act.
        $objectByHash = $repository->findFileObjectByHash('object-hash');
        $createdObject = $repository->createFileObject('created-hash', '/tmp/file.txt', 'text/plain', 123);
        $objectById = $repository->findFileObjectById(32);
        $repository->incrementFileObjectReference(31);
        $repository->decrementFileObjectReference(31);
        $repository->deleteFileObjectById(31);
        // endregion.

        // region Assert.
        $this->assertInstanceOf(FileObject::class, $objectByHash);
        $this->assertSame('object-hash', $objectByHash?->hash);
        $this->assertSame('created-hash', $createdObject->hash);
        $this->assertSame('object-id-hash', $objectById?->hash);
        $this->assertSame([['created-hash', '/tmp/file.txt', 'text/plain', 123]], $createStatement->executedParams);
        $this->assertSame([[31]], $incrementStatement->executedParams);
        $this->assertSame([[31]], $decrementStatement->executedParams);
        $this->assertSame([[31]], $deleteStatement->executedParams);
        // endregion.
    }

    public function testCreateAndFindFileMethodsMapFileData(): void {
        // region Arrange.
        $pdo = new FakePdo();
        $pdo->setLastInsertId(41);

        $createStatement = FakePdoStatement::create();
        $findByHashStatement = FakePdoStatement::create()->queueFetch($this->fileRow([
                'ID' => 41,
                'HASH' => 'file-hash',
        ]));
        $findActiveStatement = FakePdoStatement::create()->queueFetch($this->fileRow([
                'ID' => 41,
                'HASH' => 'file-hash',
                'USER_ID' => 5,
        ]));
        $findPublicStatement = FakePdoStatement::create()->queueFetchAll([
                $this->fileRow([
                        'ID' => 42,
                        'HASH' => 'public-hash',
                        'IS_PUBLIC' => 1,
                ]),
        ]);
        $largestFilesStatement = FakePdoStatement::create()->queueFetchAll([
                $this->fileRow([
                        'ID' => 43,
                        'HASH' => 'largest-hash',
                        'fo_FILE_SIZE' => 4096,
                ]),
        ]);
        $countFilesStatement = FakePdoStatement::create()->queueFetchColumn('8');
        $countDistinctStatement = FakePdoStatement::create()->queueFetchColumn('3');
        $usedSpaceStatement = FakePdoStatement::create()->queueFetchColumn('8192');

        $pdo->expectPrepare(
                'INSERT INTO file (HASH, USER_ID, FOLDER_ID, FILE_OBJECT_ID, NAME, SEARCH_INDEX_STATUS) VALUES (?, ?, ?, ?, ?, ?)',
                $createStatement
        );
        $pdo->expectPrepare($this->baseFileSelect() . ' WHERE f.HASH = :fileHash AND f.IN_TRASH = FALSE', $findByHashStatement);
        $pdo->expectPrepare($this->baseFileSelect() . ' WHERE f.USER_ID = :userId AND f.HASH = :fileHash AND f.IN_TRASH = FALSE', $findActiveStatement);
        $pdo->expectPrepare($this->baseFileSelect() . ' WHERE f.IS_PUBLIC = TRUE AND f.USER_ID = :userId AND f.IN_TRASH = FALSE', $findPublicStatement);
        $pdo->expectPrepare($this->baseFileSelect() . ' WHERE f.USER_ID = :userId AND f.IN_TRASH = FALSE ORDER BY fo.FILE_SIZE DESC LIMIT 5', $largestFilesStatement);
        $pdo->expectPrepare('SELECT COUNT(*) FROM file WHERE USER_ID = :userId', $countFilesStatement);
        $pdo->expectPrepare('SELECT COUNT(DISTINCT FILE_OBJECT_ID) FROM file WHERE USER_ID = :userId', $countDistinctStatement);
        $pdo->expectPrepare(
                '
            SELECT COALESCE(SUM(fo.FILE_SIZE), 0) AS total
            FROM file_object fo
            INNER JOIN (
                SELECT DISTINCT FILE_OBJECT_ID
                FROM file
                WHERE USER_ID = :userId
            ) f ON f.FILE_OBJECT_ID = fo.ID
        ',
                $usedSpaceStatement
        );

        $repository = new FileRepository($pdo);
        // endregion.

        // region Act.
        $fileId = $repository->createFile('file-hash', 5, 2, 31, 'report.pdf');
        $file = $repository->findByHash('file-hash');
        $activeFile = $repository->findActiveByHashForUser(5, 'file-hash');
        $publicFiles = $repository->findPublicFilesByUser(5);
        $largestFiles = $repository->findLargestFilesByUser(5, 5);
        $filesCount = $repository->countUserFiles(5);
        $distinctCount = $repository->countDistinctUserFileObjects(5);
        $usedSpace = $repository->calculateUserUsedSpace(5);
        // endregion.

        // region Assert.
        $this->assertSame(41, $fileId);
        $this->assertInstanceOf(File::class, $file);
        $this->assertSame(FileType::Pdf, $file?->fileType);
        $this->assertSame(FileIndexStatus::Indexed, $file?->searchIndexStatus);
        $this->assertSame('file-hash', $activeFile?->hash);
        $this->assertCount(1, $publicFiles);
        $this->assertTrue($publicFiles[0]->isPublic);
        $this->assertCount(1, $largestFiles);
        $this->assertSame(8, $filesCount);
        $this->assertSame(3, $distinctCount);
        $this->assertSame(8192, $usedSpace);
        $this->assertSame([['file-hash', 5, 2, 31, 'report.pdf', FileIndexStatus::Pending->value]], $createStatement->executedParams);
        // endregion.
    }

    public function testFindUserFilesAndTrashQueries(): void {
        // region Arrange.
        $pdo = new FakePdo();
        $findUserFilesStatement = FakePdoStatement::create()->queueFetchAll([
                $this->fileRow([
                        'ID' => 51,
                        'HASH' => 'allowed-hash',
                        'NAME' => 'alpha.txt',
                ]),
        ]);
        $findTrashedFilesStatement = FakePdoStatement::create()->queueFetchAll([
                $this->fileRow([
                        'ID' => 52,
                        'HASH' => 'trashed-hash',
                ]),
        ]);
        $findTrashedRecordStatement = FakePdoStatement::create()->queueFetch([
                'ID' => 52,
                'FILE_OBJECT_ID' => 31,
                'HASH' => 'trashed-hash',
        ]);
        $findFilesByFolderIdsStatement = FakePdoStatement::create()->queueFetchAll([
                ['ID' => 11, 'FILE_OBJECT_ID' => 21, 'HASH' => 'folder-file-1'],
                ['ID' => 12, 'FILE_OBJECT_ID' => 22, 'HASH' => 'folder-file-2'],
        ]);

        $pdo->expectPrepare(
                $this->baseFileSelect() . ' WHERE f.IN_TRASH = FALSE AND f.USER_ID = :userId AND f.FOLDER_ID = :folderId AND f.NAME LIKE :search AND f.HASH IN (:hash_0, :hash_1) ORDER BY f.NAME ASC',
                $findUserFilesStatement
        );
        $pdo->expectPrepare(
                $this->baseFileSelect() . ' WHERE f.USER_ID = :userId AND f.IN_TRASH = TRUE AND f.FOLDER_ID IS NULL ORDER BY f.CREATED_AT DESC',
                $findTrashedFilesStatement
        );
        $pdo->expectPrepare('SELECT ID, FILE_OBJECT_ID, HASH FROM file WHERE HASH = :hash AND IN_TRASH = TRUE', $findTrashedRecordStatement);
        $pdo->expectPrepare('SELECT ID, FILE_OBJECT_ID, HASH FROM file WHERE FOLDER_ID IN (:folder_0, :folder_1)', $findFilesByFolderIdsStatement);

        $repository = new FileRepository($pdo);
        // endregion.

        // region Act.
        $files = $repository->findUserFiles(5, 2, 'alp', ['allowed-hash', 'second-hash'], 'f.NAME', 'ASC');
        $emptyAllowedHashes = $repository->findUserFiles(5, null, null, [], 'f.CREATED_AT', 'DESC');
        $trashedFiles = $repository->findTrashedFiles(5, null, 'f.CREATED_AT', 'DESC');
        $trashedRecord = $repository->findTrashedFileRecord('trashed-hash');
        $filesByFolders = $repository->findFilesByFolderIds([9, 10]);
        $emptyFilesByFolders = $repository->findFilesByFolderIds([]);
        // endregion.

        // region Assert.
        $this->assertCount(1, $files);
        $this->assertSame('allowed-hash', $files[0]->hash);
        $this->assertSame([], $emptyAllowedHashes);
        $this->assertCount(1, $trashedFiles);
        $this->assertSame(['id' => 52, 'fileObjectId' => 31, 'hash' => 'trashed-hash'], $trashedRecord);
        $this->assertSame([
                ['id' => 11, 'fileObjectId' => 21, 'hash' => 'folder-file-1'],
                ['id' => 12, 'fileObjectId' => 22, 'hash' => 'folder-file-2'],
        ], $filesByFolders);
        $this->assertSame([], $emptyFilesByFolders);
        $this->assertSame([[
                'userId' => 5,
                'folderId' => 2,
                'search' => '%alp%',
                'hash_0' => 'allowed-hash',
                'hash_1' => 'second-hash',
        ]], $findUserFilesStatement->executedParams);
        // endregion.
    }

    public function testMutationMethodsExecuteExpectedQueries(): void {
        // region Arrange.
        $pdo = new FakePdo();
        $moveStatement = FakePdoStatement::create()->setRowCount(1);
        $markInTrashWithUserStatement = FakePdoStatement::create()->setRowCount(1);
        $markInTrashWithoutUserStatement = FakePdoStatement::create()->setRowCount(2);
        $restoreWithUserStatement = FakePdoStatement::create()->setRowCount(1);
        $restoreWithoutUserStatement = FakePdoStatement::create()->setRowCount(2);
        $findFileIdWithFiltersStatement = FakePdoStatement::create()->queueFetchColumn('51');
        $findFileIdWithoutFiltersStatement = FakePdoStatement::create()->queueFetchColumn('52');
        $addToTrashFirstStatement = FakePdoStatement::create();
        $addToTrashSecondStatement = FakePdoStatement::create();
        $removeFromTrashFirstStatement = FakePdoStatement::create();
        $removeFromTrashSecondStatement = FakePdoStatement::create();
        $markByIdsStatement = FakePdoStatement::create()->setRowCount(2);
        $restoreByIdsStatement = FakePdoStatement::create()->setRowCount(2);
        $deleteFileByIdStatement = FakePdoStatement::create();
        $markPublicStatement = FakePdoStatement::create();
        $incrementPublicCounterStatement = FakePdoStatement::create();
        $markPendingStatement = FakePdoStatement::create()->setRowCount(1);
        $markIndexedStatement = FakePdoStatement::create()->setRowCount(1);
        $releaseIndexingStatement = FakePdoStatement::create()->setRowCount(1);

        $pdo->expectPrepare('UPDATE file SET FOLDER_ID = :folderId WHERE USER_ID = :uid AND HASH = :hash AND IN_TRASH = FALSE', $moveStatement);
        $pdo->expectPrepare('UPDATE file SET IN_TRASH = TRUE WHERE HASH = :hash AND IN_TRASH = FALSE AND USER_ID = :userId', $markInTrashWithUserStatement);
        $pdo->expectPrepare('UPDATE file SET IN_TRASH = TRUE WHERE HASH = :hash AND IN_TRASH = FALSE', $markInTrashWithoutUserStatement);
        $pdo->expectPrepare('UPDATE file SET IN_TRASH = FALSE WHERE HASH = :hash AND IN_TRASH = TRUE AND USER_ID = :userId', $restoreWithUserStatement);
        $pdo->expectPrepare('UPDATE file SET IN_TRASH = FALSE WHERE HASH = :hash AND IN_TRASH = TRUE', $restoreWithoutUserStatement);
        $pdo->expectPrepare('SELECT ID FROM file WHERE HASH = :hash AND USER_ID = :userId AND IN_TRASH = :inTrash', $findFileIdWithFiltersStatement);
        $pdo->expectPrepare('SELECT ID FROM file WHERE HASH = :hash', $findFileIdWithoutFiltersStatement);
        $pdo->expectPrepare('INSERT INTO trash (FILE_ID) VALUES (?)', $addToTrashFirstStatement);
        $pdo->expectPrepare('INSERT INTO trash (FILE_ID) VALUES (?)', $addToTrashSecondStatement);
        $pdo->expectPrepare('DELETE FROM trash WHERE FILE_ID = ?', $removeFromTrashFirstStatement);
        $pdo->expectPrepare('DELETE FROM trash WHERE FILE_ID = ?', $removeFromTrashSecondStatement);
        $pdo->expectPrepare('UPDATE file SET IN_TRASH = TRUE WHERE ID IN (:file_0, :file_1)', $markByIdsStatement);
        $pdo->expectPrepare('UPDATE file SET IN_TRASH = FALSE WHERE ID IN (:file_0, :file_1)', $restoreByIdsStatement);
        $pdo->expectPrepare('DELETE FROM file WHERE ID = ?', $deleteFileByIdStatement);
        $pdo->expectPrepare('UPDATE file SET IS_PUBLIC = TRUE WHERE ID = ?', $markPublicStatement);
        $pdo->expectPrepare('UPDATE file SET PUBLIC_VISITS_NUM = PUBLIC_VISITS_NUM + 1, LAST_VISITED_DATE = CURRENT_TIMESTAMP WHERE ID = ?', $incrementPublicCounterStatement);
        $pdo->expectPrepare('UPDATE file SET SEARCH_INDEX_STATUS = :status WHERE HASH = :hash AND IN_TRASH = FALSE AND USER_ID = :userId', $markPendingStatement);
        $pdo->expectPrepare(
                'UPDATE file SET SEARCH_INDEX_STATUS = :indexedStatus WHERE ID = :fileId AND SEARCH_INDEX_STATUS = :processingStatus',
                $markIndexedStatement
        );
        $pdo->expectPrepare(
                'UPDATE file SET SEARCH_INDEX_STATUS = :pendingStatus WHERE ID = :fileId AND SEARCH_INDEX_STATUS = :processingStatus',
                $releaseIndexingStatement
        );

        $repository = new FileRepository($pdo);
        // endregion.

        // region Act.
        $moved = $repository->moveActiveFile(5, 'file-hash', 3);
        $markedWithUser = $repository->markInTrash('file-hash', 5);
        $markedWithoutUser = $repository->markInTrash('file-hash');
        $restoredWithUser = $repository->restoreFromTrash('file-hash', 5);
        $restoredWithoutUser = $repository->restoreFromTrash('file-hash');
        $fileIdWithFilters = $repository->findFileIdByHash('file-hash', 5, true);
        $fileIdWithoutFilters = $repository->findFileIdByHash('another-hash');
        $repository->addToTrash(11);
        $repository->addToTrashMany([12]);
        $repository->removeFromTrash(11);
        $repository->removeFromTrashMany([12]);
        $markedByIds = $repository->markInTrashByIds([11, 12]);
        $restoredByIds = $repository->restoreFromTrashByIds([11, 12]);
        $emptyMarked = $repository->markInTrashByIds([]);
        $emptyRestored = $repository->restoreFromTrashByIds([]);
        $repository->deleteFileById(70);
        $repository->markPublic(80);
        $repository->incrementPublicCounter(80);
        $markedPending = $repository->markFilePendingIndexing('file-hash', 5);
        $markedIndexed = $repository->markFileIndexed(70);
        $releasedIndexing = $repository->releaseFileIndexing(70);
        // endregion.

        // region Assert.
        $this->assertTrue($moved);
        $this->assertSame(1, $markedWithUser);
        $this->assertSame(2, $markedWithoutUser);
        $this->assertSame(1, $restoredWithUser);
        $this->assertSame(2, $restoredWithoutUser);
        $this->assertSame(51, $fileIdWithFilters);
        $this->assertSame(52, $fileIdWithoutFilters);
        $this->assertSame(2, $markedByIds);
        $this->assertSame(2, $restoredByIds);
        $this->assertSame(0, $emptyMarked);
        $this->assertSame(0, $emptyRestored);
        $this->assertSame(1, $markedPending);
        $this->assertTrue($markedIndexed);
        $this->assertTrue($releasedIndexing);
        $this->assertSame([['folderId' => 3, 'uid' => 5, 'hash' => 'file-hash']], $moveStatement->executedParams);
        $this->assertSame([['hash' => 'file-hash', 'userId' => 5]], $markInTrashWithUserStatement->executedParams);
        $this->assertSame([['hash' => 'file-hash']], $markInTrashWithoutUserStatement->executedParams);
        $this->assertSame([['hash' => 'file-hash', 'userId' => 5]], $restoreWithUserStatement->executedParams);
        $this->assertSame([['hash' => 'file-hash']], $restoreWithoutUserStatement->executedParams);
        $this->assertSame([['hash' => 'file-hash', 'userId' => 5, 'inTrash' => true]], $findFileIdWithFiltersStatement->executedParams);
        $this->assertSame([['hash' => 'another-hash']], $findFileIdWithoutFiltersStatement->executedParams);
        $this->assertSame([[11]], $addToTrashFirstStatement->executedParams);
        $this->assertSame([[12]], $addToTrashSecondStatement->executedParams);
        $this->assertSame([[11]], $removeFromTrashFirstStatement->executedParams);
        $this->assertSame([[12]], $removeFromTrashSecondStatement->executedParams);
        $this->assertSame([['file_0' => 11, 'file_1' => 12]], $markByIdsStatement->executedParams);
        $this->assertSame([['file_0' => 11, 'file_1' => 12]], $restoreByIdsStatement->executedParams);
        $this->assertSame([[70]], $deleteFileByIdStatement->executedParams);
        $this->assertSame([[80]], $markPublicStatement->executedParams);
        $this->assertSame([[80]], $incrementPublicCounterStatement->executedParams);
        $this->assertSame([[
                'status' => FileIndexStatus::Pending->value,
                'hash' => 'file-hash',
                'userId' => 5,
        ]], $markPendingStatement->executedParams);
        $this->assertSame([[
                'indexedStatus' => FileIndexStatus::Indexed->value,
                'fileId' => 70,
                'processingStatus' => FileIndexStatus::Processing->value,
        ]], $markIndexedStatement->executedParams);
        $this->assertSame([[
                'pendingStatus' => FileIndexStatus::Pending->value,
                'fileId' => 70,
                'processingStatus' => FileIndexStatus::Processing->value,
        ]], $releaseIndexingStatement->executedParams);
        // endregion.
    }

    public function testClaimFilesForIndexingClaimsPendingRecordsInOrder(): void {
        // region Arrange.
        $pdo = new FakePdo();
        $selectPendingIdsStatement = FakePdoStatement::create()->queueFetchAll([
                ['ID' => 51],
                ['ID' => 52],
        ]);
        $claimFirstStatement = FakePdoStatement::create()->setRowCount(1);
        $claimSecondStatement = FakePdoStatement::create()->setRowCount(1);
        $fetchClaimedFilesStatement = FakePdoStatement::create()->queueFetchAll([
                $this->fileRow([
                        'ID' => 51,
                        'HASH' => 'pending-first',
                        'SEARCH_INDEX_STATUS' => FileIndexStatus::Processing->value,
                ]),
                $this->fileRow([
                        'ID' => 52,
                        'HASH' => 'pending-second',
                        'SEARCH_INDEX_STATUS' => FileIndexStatus::Processing->value,
                ]),
        ]);

        $pdo->expectPrepare(
                'SELECT ID FROM file WHERE IN_TRASH = FALSE AND SEARCH_INDEX_STATUS = :status ORDER BY ID ASC LIMIT 2',
                $selectPendingIdsStatement
        );
        $pdo->expectPrepare(
                'UPDATE file SET SEARCH_INDEX_STATUS = :processingStatus WHERE ID = :fileId AND IN_TRASH = FALSE AND SEARCH_INDEX_STATUS = :pendingStatus',
                $claimFirstStatement
        );
        $pdo->expectPrepare(
                'UPDATE file SET SEARCH_INDEX_STATUS = :processingStatus WHERE ID = :fileId AND IN_TRASH = FALSE AND SEARCH_INDEX_STATUS = :pendingStatus',
                $claimSecondStatement
        );
        $pdo->expectPrepare(
                $this->baseFileSelect() . ' WHERE f.ID IN (:file_0, :file_1) ORDER BY f.ID ASC',
                $fetchClaimedFilesStatement
        );

        $repository = new FileRepository($pdo);
        // endregion.

        // region Act.
        $claimedFiles = $repository->claimFilesForIndexing(2);
        // endregion.

        // region Assert.
        $this->assertCount(2, $claimedFiles);
        $this->assertSame(['pending-first', 'pending-second'], array_map(fn(File $file): string => $file->hash, $claimedFiles));
        $this->assertSame([['status' => FileIndexStatus::Pending->value]], $selectPendingIdsStatement->executedParams);
        $this->assertSame([[
                'processingStatus' => FileIndexStatus::Processing->value,
                'fileId' => 51,
                'pendingStatus' => FileIndexStatus::Pending->value,
        ]], $claimFirstStatement->executedParams);
        $this->assertSame([[
                'processingStatus' => FileIndexStatus::Processing->value,
                'fileId' => 52,
                'pendingStatus' => FileIndexStatus::Pending->value,
        ]], $claimSecondStatement->executedParams);
        $this->assertSame([['file_0' => 51, 'file_1' => 52]], $fetchClaimedFilesStatement->executedParams);
        // endregion.
    }

    private function baseFileSelect(): string {
        return '
            SELECT
                f.ID,
                f.HASH,
                f.USER_ID,
                f.FOLDER_ID,
                f.NAME,
                f.CREATED_AT,
                f.SEARCH_INDEX_STATUS,
                f.PUBLIC_VISITS_NUM,
                f.LAST_VISITED_DATE,
                f.IS_PUBLIC,
                fo.ID AS fo_ID,
                fo.HASH AS fo_HASH,
                fo.STORAGE_PATH AS fo_STORAGE_PATH,
                fo.CONTENT_TYPE AS fo_CONTENT_TYPE,
                fo.FILE_SIZE AS fo_FILE_SIZE,
                fo.REF_COUNT AS fo_REF_COUNT,
                fo.CREATED_AT AS fo_CREATED_AT
            FROM file f
            INNER JOIN file_object fo ON f.FILE_OBJECT_ID = fo.ID
        ';
    }

    private function fileObjectRow(array $overrides = []): array {
        return array_merge([
                'ID' => 31,
                'HASH' => 'object-hash',
                'STORAGE_PATH' => '/tmp/file.txt',
                'CONTENT_TYPE' => 'text/plain',
                'FILE_SIZE' => 123,
                'REF_COUNT' => 1,
                'CREATED_AT' => '2025-01-01 09:00:00',
        ], $overrides);
    }

    private function fileRow(array $overrides = []): array {
        return array_merge([
                'ID' => 41,
                'HASH' => 'file-hash',
                'USER_ID' => 5,
                'FOLDER_ID' => 2,
                'NAME' => 'report.pdf',
                'CREATED_AT' => '2025-01-01 10:00:00',
                'SEARCH_INDEX_STATUS' => FileIndexStatus::Indexed->value,
                'PUBLIC_VISITS_NUM' => 3,
                'LAST_VISITED_DATE' => '2025-01-02 10:00:00',
                'IS_PUBLIC' => 0,
                'fo_ID' => 31,
                'fo_HASH' => 'object-hash',
                'fo_STORAGE_PATH' => '/tmp/file.txt',
                'fo_CONTENT_TYPE' => 'application/pdf',
                'fo_FILE_SIZE' => 2048,
                'fo_REF_COUNT' => 1,
                'fo_CREATED_AT' => '2025-01-01 09:00:00',
        ], $overrides);
    }
}
