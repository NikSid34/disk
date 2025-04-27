<?php


namespace app\repository;


use app\entity\File;
use app\entity\FileObject;
use app\enum\FileIndexStatus;
use app\enum\FileType;
use DateTime;
use PDO;
use RuntimeException;


class FileRepository {
    public function __construct(
            private readonly PDO $pdo
    ) {
    }

    public function findFileObjectByHash(string $hash): ?FileObject {
        $stmt = $this->pdo->prepare('SELECT * FROM file_object WHERE HASH = :hash');
        $stmt->execute(array('hash' => $hash));

        $row = $stmt->fetch();

        return $row !== false ? $this->mapFileObject($row) : null;
    }

    public function findFileObjectById(int $fileObjectId): ?FileObject {
        $stmt = $this->pdo->prepare('SELECT * FROM file_object WHERE ID = :id');
        $stmt->execute(array('id' => $fileObjectId));

        $row = $stmt->fetch();

        return $row !== false ? $this->mapFileObject($row) : null;
    }

    public function createFileObject(string $hash, string $storagePath, string $contentType, int $fileSize): FileObject {
        $stmt = $this->pdo->prepare('INSERT INTO file_object (HASH, STORAGE_PATH, CONTENT_TYPE, FILE_SIZE) VALUES (?, ?, ?, ?)');
        $stmt->execute(array($hash, $storagePath, $contentType, $fileSize));

        $fileObject = $this->findFileObjectById((int) $this->pdo->lastInsertId());
        if ($fileObject === null) {
            throw new RuntimeException('Failed to create file object');
        }

        return $fileObject;
    }

    public function incrementFileObjectReference(int $fileObjectId): void {
        $stmt = $this->pdo->prepare('UPDATE file_object SET REF_COUNT = REF_COUNT + 1 WHERE ID = ?');
        $stmt->execute(array($fileObjectId));
    }

    public function decrementFileObjectReference(int $fileObjectId): void {
        $stmt = $this->pdo->prepare('UPDATE file_object SET REF_COUNT = REF_COUNT - 1 WHERE ID = ?');
        $stmt->execute(array($fileObjectId));
    }

    public function deleteFileObjectById(int $fileObjectId): void {
        $stmt = $this->pdo->prepare('DELETE FROM file_object WHERE ID = ?');
        $stmt->execute(array($fileObjectId));
    }

    public function createFile(string $hash, int $userId, ?int $folderId, int $fileObjectId, string $name): int {
        $stmt = $this->pdo->prepare(
                'INSERT INTO file (HASH, USER_ID, FOLDER_ID, FILE_OBJECT_ID, NAME, SEARCH_INDEX_STATUS) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(array($hash, $userId, $folderId, $fileObjectId, $name, FileIndexStatus::Pending->value));

        return (int) $this->pdo->lastInsertId();
    }

    public function findByHash(string $fileHash): ?File {
        $sql = $this->baseFileSelect() . ' WHERE f.HASH = :fileHash AND f.IN_TRASH = FALSE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array('fileHash' => $fileHash));

        $row = $stmt->fetch();

        return $row !== false ? $this->mapFile($row) : null;
    }

    public function findActiveByHashForUser(int $userId, string $fileHash): ?File {
        $sql = $this->baseFileSelect() . ' WHERE f.USER_ID = :userId AND f.HASH = :fileHash AND f.IN_TRASH = FALSE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array(
                'userId'   => $userId,
                'fileHash' => $fileHash,
        ));

        $row = $stmt->fetch();

        return $row !== false ? $this->mapFile($row) : null;
    }

    public function findUserFiles(
            int $userId,
            ?int $folderId,
            ?string $search,
            ?array $allowedHashes,
            string $sortBy,
            string $sortDirection
    ): array {
        if ($allowedHashes !== null && $allowedHashes === array()) {
            return array();
        }

        $sql = $this->baseFileSelect() . ' WHERE f.IN_TRASH = FALSE';
        $params = array();

        if ($userId !== 0) {
            $sql .= ' AND f.USER_ID = :userId';
            $params['userId'] = $userId;
        }

        if ($folderId === null) {
            $sql .= ' AND f.FOLDER_ID IS NULL';
        } else {
            $sql .= ' AND f.FOLDER_ID = :folderId';
            $params['folderId'] = $folderId;
        }

        if (!empty($search)) {
            $sql .= ' AND f.NAME LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        if ($allowedHashes !== null) {
            $placeholders = array();
            foreach ($allowedHashes as $index => $hash) {
                $key = ':hash_' . $index;
                $placeholders[] = $key;
                $params['hash_' . $index] = $hash;
            }

            $sql .= ' AND f.HASH IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= " ORDER BY {$sortBy} {$sortDirection}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn(array $row): File => $this->mapFile($row), $stmt->fetchAll());
    }

    public function findPublicFilesByUser(int $userId): array {
        $sql = $this->baseFileSelect() . ' WHERE f.IS_PUBLIC = TRUE AND f.USER_ID = :userId AND f.IN_TRASH = FALSE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array('userId' => $userId));

        return array_map(fn(array $row): File => $this->mapFile($row), $stmt->fetchAll());
    }

    public function findLargestFilesByUser(int $userId, int $limit = 3): array {
        $sql = $this->baseFileSelect() . ' WHERE f.USER_ID = :userId AND f.IN_TRASH = FALSE ORDER BY fo.FILE_SIZE DESC LIMIT ' . $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array('userId' => $userId));

        return array_map(fn(array $row): File => $this->mapFile($row), $stmt->fetchAll());
    }

    public function countUserFiles(int $userId): int {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM file WHERE USER_ID = :userId');
        $stmt->execute(array('userId' => $userId));

        return (int) $stmt->fetchColumn();
    }

    public function countDistinctUserFileObjects(int $userId): int {
        $stmt = $this->pdo->prepare('SELECT COUNT(DISTINCT FILE_OBJECT_ID) FROM file WHERE USER_ID = :userId');
        $stmt->execute(array('userId' => $userId));

        return (int) $stmt->fetchColumn();
    }

    public function calculateUserUsedSpace(int $userId): int {
        $sql = '
            SELECT COALESCE(SUM(fo.FILE_SIZE), 0) AS total
            FROM file_object fo
            INNER JOIN (
                SELECT DISTINCT FILE_OBJECT_ID
                FROM file
                WHERE USER_ID = :userId
            ) f ON f.FILE_OBJECT_ID = fo.ID
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array('userId' => $userId));

        return (int) $stmt->fetchColumn();
    }

    public function moveActiveFile(int $userId, string $fileHash, int $folderId): bool {
        $stmt = $this->pdo->prepare('UPDATE file SET FOLDER_ID = :folderId WHERE USER_ID = :uid AND HASH = :hash AND IN_TRASH = FALSE');
        $stmt->execute(array(
                'folderId' => $folderId,
                'uid'      => $userId,
                'hash'     => $fileHash,
        ));

        return $stmt->rowCount() > 0;
    }

    public function markFilePendingIndexing(string $fileHash, ?int $userId = null): int {
        $sql = 'UPDATE file SET SEARCH_INDEX_STATUS = :status WHERE HASH = :hash AND IN_TRASH = FALSE';
        $params = array(
                'status' => FileIndexStatus::Pending->value,
                'hash' => $fileHash,
        );

        if ($userId !== null) {
            $sql .= ' AND USER_ID = :userId';
            $params['userId'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function markFileIndexed(int $fileId): bool {
        $stmt = $this->pdo->prepare(
                'UPDATE file SET SEARCH_INDEX_STATUS = :indexedStatus WHERE ID = :fileId AND SEARCH_INDEX_STATUS = :processingStatus'
        );
        $stmt->execute(array(
                'indexedStatus' => FileIndexStatus::Indexed->value,
                'fileId' => $fileId,
                'processingStatus' => FileIndexStatus::Processing->value,
        ));

        return $stmt->rowCount() > 0;
    }

    public function releaseFileIndexing(int $fileId): bool {
        $stmt = $this->pdo->prepare(
                'UPDATE file SET SEARCH_INDEX_STATUS = :pendingStatus WHERE ID = :fileId AND SEARCH_INDEX_STATUS = :processingStatus'
        );
        $stmt->execute(array(
                'pendingStatus' => FileIndexStatus::Pending->value,
                'fileId' => $fileId,
                'processingStatus' => FileIndexStatus::Processing->value,
        ));

        return $stmt->rowCount() > 0;
    }

    /**
     * @return File[]
     */
    public function claimFilesForIndexing(int $limit): array {
        $limit = max(1, $limit);

        $stmt = $this->pdo->prepare(
                'SELECT ID FROM file WHERE IN_TRASH = FALSE AND SEARCH_INDEX_STATUS = :status ORDER BY ID ASC LIMIT ' . $limit
        );
        $stmt->execute(array(
                'status' => FileIndexStatus::Pending->value,
        ));

        $candidateIds = array_map(
                static fn(array $row): int => (int) $row['ID'],
                $stmt->fetchAll()
        );
        if ($candidateIds === array()) {
            return array();
        }

        $claimedIds = array();
        foreach ($candidateIds as $fileId) {
            $claimStmt = $this->pdo->prepare(
                    'UPDATE file SET SEARCH_INDEX_STATUS = :processingStatus WHERE ID = :fileId AND IN_TRASH = FALSE AND SEARCH_INDEX_STATUS = :pendingStatus'
            );
            $claimStmt->execute(array(
                    'processingStatus' => FileIndexStatus::Processing->value,
                    'fileId' => $fileId,
                    'pendingStatus' => FileIndexStatus::Pending->value,
            ));

            if ($claimStmt->rowCount() > 0) {
                $claimedIds[] = $fileId;
            }
        }

        return $this->findFilesByIds($claimedIds);
    }

    public function markInTrash(string $fileHash, ?int $userId = null): int {
        $sql = 'UPDATE file SET IN_TRASH = TRUE WHERE HASH = :hash AND IN_TRASH = FALSE';
        $params = array('hash' => $fileHash);

        if ($userId !== null) {
            $sql .= ' AND USER_ID = :userId';
            $params['userId'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function restoreFromTrash(string $fileHash, ?int $userId = null): int {
        $sql = 'UPDATE file SET IN_TRASH = FALSE WHERE HASH = :hash AND IN_TRASH = TRUE';
        $params = array('hash' => $fileHash);

        if ($userId !== null) {
            $sql .= ' AND USER_ID = :userId';
            $params['userId'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function findFileIdByHash(string $fileHash, ?int $userId = null, ?bool $inTrash = null): ?int {
        $sql = 'SELECT ID FROM file WHERE HASH = :hash';
        $params = array('hash' => $fileHash);

        if ($userId !== null) {
            $sql .= ' AND USER_ID = :userId';
            $params['userId'] = $userId;
        }

        if ($inTrash !== null) {
            $sql .= ' AND IN_TRASH = :inTrash';
            $params['inTrash'] = $inTrash;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    public function addToTrash(int $fileId): void {
        $stmt = $this->pdo->prepare('INSERT INTO trash (FILE_ID) VALUES (?)');
        $stmt->execute(array($fileId));
    }

    /**
     * @param int[] $fileIds
     */
    public function addToTrashMany(array $fileIds): void {
        foreach ($fileIds as $fileId) {
            $this->addToTrash($fileId);
        }
    }

    public function removeFromTrash(int $fileId): void {
        $stmt = $this->pdo->prepare('DELETE FROM trash WHERE FILE_ID = ?');
        $stmt->execute(array($fileId));
    }

    /**
     * @param int[] $fileIds
     */
    public function removeFromTrashMany(array $fileIds): void {
        foreach ($fileIds as $fileId) {
            $this->removeFromTrash($fileId);
        }
    }

    /**
     * @param int[] $fileIds
     */
    public function markInTrashByIds(array $fileIds): int {
        if ($fileIds === array()) {
            return 0;
        }

        $placeholders = array();
        $params = array();
        foreach (array_values($fileIds) as $index => $fileId) {
            $key = ':file_' . $index;
            $placeholders[] = $key;
            $params['file_' . $index] = $fileId;
        }

        $stmt = $this->pdo->prepare(
                'UPDATE file SET IN_TRASH = TRUE WHERE ID IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * @param int[] $fileIds
     */
    public function restoreFromTrashByIds(array $fileIds): int {
        if ($fileIds === array()) {
            return 0;
        }

        $placeholders = array();
        $params = array();
        foreach (array_values($fileIds) as $index => $fileId) {
            $key = ':file_' . $index;
            $placeholders[] = $key;
            $params['file_' . $index] = $fileId;
        }

        $stmt = $this->pdo->prepare(
                'UPDATE file SET IN_TRASH = FALSE WHERE ID IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function findTrashedFileRecord(string $fileHash): ?array {
        $stmt = $this->pdo->prepare('SELECT ID, FILE_OBJECT_ID, HASH FROM file WHERE HASH = :hash AND IN_TRASH = TRUE');
        $stmt->execute(array('hash' => $fileHash));

        $row = $stmt->fetch();

        return $row !== false ? array(
                'id'           => (int) $row['ID'],
                'fileObjectId' => (int) $row['FILE_OBJECT_ID'],
                'hash'         => (string) $row['HASH'],
        ) : null;
    }

    public function deleteFileById(int $fileId): void {
        $stmt = $this->pdo->prepare('DELETE FROM file WHERE ID = ?');
        $stmt->execute(array($fileId));
    }

    public function findFilesByFolderIds(array $folderIds): array {
        if ($folderIds === array()) {
            return array();
        }

        $placeholders = array();
        $params = array();

        foreach (array_values($folderIds) as $index => $folderId) {
            $key = ':folder_' . $index;
            $placeholders[] = $key;
            $params['folder_' . $index] = $folderId;
        }

        $sql = 'SELECT ID, FILE_OBJECT_ID, HASH FROM file WHERE FOLDER_ID IN (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(
                fn(array $row): array => array(
                        'id'           => (int) $row['ID'],
                        'fileObjectId' => (int) $row['FILE_OBJECT_ID'],
                        'hash'         => (string) $row['HASH'],
                ),
                $stmt->fetchAll()
        );
    }

    public function findTrashedFiles(
            int $userId,
            ?int $folderId,
            string $sortBy,
            string $sortDirection
    ): array {
        $sql = $this->baseFileSelect() . ' WHERE f.USER_ID = :userId AND f.IN_TRASH = TRUE';

        $params = array('userId' => $userId);

        if ($folderId === null) {
            $sql .= ' AND f.FOLDER_ID IS NULL';
        } else {
            $sql .= ' AND f.FOLDER_ID = :folderId';
            $params['folderId'] = $folderId;
        }

        $sql .= " ORDER BY {$sortBy} {$sortDirection}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn(array $row): File => $this->mapFile($row), $stmt->fetchAll());
    }

    public function markPublic(int $fileId): void {
        $stmt = $this->pdo->prepare('UPDATE file SET IS_PUBLIC = TRUE WHERE ID = ?');
        $stmt->execute(array($fileId));
    }

    public function incrementPublicCounter(int $fileId): void {
        $stmt = $this->pdo->prepare('UPDATE file SET PUBLIC_VISITS_NUM = PUBLIC_VISITS_NUM + 1, LAST_VISITED_DATE = CURRENT_TIMESTAMP WHERE ID = ?');
        $stmt->execute(array($fileId));
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

    private function mapFileObject(array $row): FileObject {
        return new FileObject(
                (int) $row['ID'],
                $row['HASH'],
                $row['STORAGE_PATH'],
                $row['CONTENT_TYPE'],
                (int) $row['FILE_SIZE'],
                (int) $row['REF_COUNT'],
                new DateTime($row['CREATED_AT'])
        );
    }

    private function mapFile(array $row): File {
        return new File(
                (int) $row['ID'],
                $row['HASH'],
                (int) $row['USER_ID'],
                $row['FOLDER_ID'] !== null ? (int) $row['FOLDER_ID'] : null,
                $row['NAME'],
                strtolower(pathinfo($row['NAME'], PATHINFO_EXTENSION)),
                FileType::fromFileName($row['NAME']),
                new DateTime($row['CREATED_AT']),
                new FileObject(
                        (int) $row['fo_ID'],
                        $row['fo_HASH'],
                        $row['fo_STORAGE_PATH'],
                        $row['fo_CONTENT_TYPE'],
                        (int) $row['fo_FILE_SIZE'],
                        (int) $row['fo_REF_COUNT'],
                        new DateTime($row['fo_CREATED_AT'])
                ),
                null,
                (int) $row['PUBLIC_VISITS_NUM'],
                (bool) $row['IS_PUBLIC'],
                FileIndexStatus::from((int) $row['SEARCH_INDEX_STATUS']),
                isset($row['LAST_VISITED_DATE']) ? new DateTime($row['LAST_VISITED_DATE']) : null
        );
    }

    /**
     * @param int[] $fileIds
     * @return File[]
     */
    private function findFilesByIds(array $fileIds): array {
        if ($fileIds === array()) {
            return array();
        }

        $placeholders = array();
        $params = array();
        foreach (array_values($fileIds) as $index => $fileId) {
            $key = ':file_' . $index;
            $placeholders[] = $key;
            $params['file_' . $index] = $fileId;
        }

        $sql = $this->baseFileSelect() . ' WHERE f.ID IN (' . implode(', ', $placeholders) . ') ORDER BY f.ID ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn(array $row): File => $this->mapFile($row), $stmt->fetchAll());
    }
}
