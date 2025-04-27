<?php


namespace app\repository;


use app\entity\Folder;
use DateTime;
use PDO;


class FolderRepository {
    public function __construct(
            private readonly PDO $pdo
    ) {
    }

    public function create(string $hash, int $userId, ?int $parentId, string $name): int {
        $stmt = $this->pdo->prepare('INSERT INTO folder (HASH, USER_ID, PARENT_ID, NAME) VALUES (:hash, :userId, :parentId, :name)');
        $stmt->execute(array(
                'hash'     => $hash,
                'userId'   => $userId,
                'parentId' => $parentId,
                'name'     => $name,
        ));

        return (int) $this->pdo->lastInsertId();
    }

    public function findByHash(string $folderHash): ?Folder {
        $stmt = $this->pdo->prepare('SELECT * FROM folder WHERE HASH = :folderHash');
        $stmt->execute(array('folderHash' => $folderHash));

        $row = $stmt->fetch();

        return $row !== false ? $this->mapFolder($row) : null;
    }

    public function findByIdAndUser(int $folderId, int $userId): ?array {
        $stmt = $this->pdo->prepare('SELECT ID, HASH, NAME, PARENT_ID FROM folder WHERE ID = :id AND USER_ID = :userId');
        $stmt->execute(array(
                'id'     => $folderId,
                'userId' => $userId,
        ));

        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    public function findUserFolders(
            int $userId,
            ?int $parentId,
            string $sortBy,
            string $sortDirection,
            ?string $search
    ): array {
        $sql = '
            SELECT 
                f.*,
                COALESCE(SUM(fo.FILE_SIZE), 0) AS total_size
            FROM folder f
            LEFT JOIN file fi ON fi.FOLDER_ID = f.ID AND fi.USER_ID = f.USER_ID AND fi.IN_TRASH = FALSE
            LEFT JOIN file_object fo ON fo.ID = fi.FILE_OBJECT_ID
            WHERE f.IN_TRASH = FALSE
              AND f.USER_ID = :userId
        ';

        $params = array('userId' => $userId);

        if ($parentId === null) {
            $sql .= ' AND f.PARENT_ID IS NULL';
        } else {
            $sql .= ' AND f.PARENT_ID = :parentId';
            $params['parentId'] = $parentId;
        }

        if (!empty($search)) {
            $sql .= ' AND f.NAME LIKE :search';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= " GROUP BY f.ID ORDER BY {$sortBy} {$sortDirection}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn(array $row): Folder => $this->mapFolder($row), $stmt->fetchAll());
    }

    public function findPublicByUser(int $userId): array {
        $stmt = $this->pdo->prepare('SELECT * FROM folder WHERE IS_PUBLIC = TRUE AND USER_ID = :userId AND IN_TRASH = FALSE');
        $stmt->execute(array('userId' => $userId));

        return array_map(fn(array $row): Folder => $this->mapFolder($row), $stmt->fetchAll());
    }

    public function findNameByHashAndUser(string $hash, int $userId): ?string {
        $stmt = $this->pdo->prepare('SELECT NAME FROM folder WHERE HASH = :hash AND USER_ID = :userId');
        $stmt->execute(array(
                'hash'   => $hash,
                'userId' => $userId,
        ));

        $name = $stmt->fetchColumn();

        return $name !== false ? (string) $name : null;
    }

    public function findTrashedFolders(int $userId, ?int $parentId, string $sortBy, string $sortDirection): array {
        $sql = '
            SELECT 
                f.*,
                COALESCE(SUM(fo.FILE_SIZE), 0) AS total_size
            FROM folder f
            LEFT JOIN file fi ON fi.FOLDER_ID = f.ID AND fi.USER_ID = f.USER_ID AND fi.IN_TRASH = TRUE
            LEFT JOIN file_object fo ON fo.ID = fi.FILE_OBJECT_ID
            WHERE f.USER_ID = :userId
              AND f.IN_TRASH = TRUE
        ';

        $params = array('userId' => $userId);

        if ($parentId === null) {
            $sql .= ' AND f.PARENT_ID IS NULL';
        } else {
            $sql .= ' AND f.PARENT_ID = :parentId';
            $params['parentId'] = $parentId;
        }

        $sql .= " GROUP BY f.ID ORDER BY {$sortBy} {$sortDirection}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(fn(array $row): Folder => $this->mapFolder($row), $stmt->fetchAll());
    }

    public function markInTrash(string $folderHash, ?int $userId = null): int {
        $sql = 'UPDATE folder SET IN_TRASH = TRUE WHERE HASH = :hash AND IN_TRASH = FALSE';
        $params = array('hash' => $folderHash);

        if ($userId !== null) {
            $sql .= ' AND USER_ID = :userId';
            $params['userId'] = $userId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function restoreFromTrash(int $userId, string $folderHash): int {
        $stmt = $this->pdo->prepare('UPDATE folder SET IN_TRASH = FALSE WHERE USER_ID = :uid AND HASH = :hash AND IN_TRASH = TRUE');
        $stmt->execute(array(
                'uid'  => $userId,
                'hash' => $folderHash,
        ));

        return $stmt->rowCount();
    }

    public function findIdByHash(string $folderHash, ?int $userId = null, ?bool $inTrash = null): ?int {
        $sql = 'SELECT ID FROM folder WHERE HASH = :hash';
        $params = array('hash' => $folderHash);

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

    public function addToTrash(int $folderId): void {
        $stmt = $this->pdo->prepare('
            INSERT INTO trash (FOLDER_ID)
            SELECT :folderId
            WHERE NOT EXISTS (
                SELECT 1
                FROM trash
                WHERE FOLDER_ID = :folderId
            )
        ');
        $stmt->execute(array('folderId' => $folderId));
    }

    /**
     * @param int[] $folderIds
     */
    public function addToTrashMany(array $folderIds): void {
        foreach ($folderIds as $folderId) {
            $this->addToTrash($folderId);
        }
    }

    public function removeFromTrash(int $folderId): void {
        $stmt = $this->pdo->prepare('DELETE FROM trash WHERE FOLDER_ID = ?');
        $stmt->execute(array($folderId));
    }

    /**
     * @param int[] $folderIds
     */
    public function removeFromTrashMany(array $folderIds): void {
        foreach ($folderIds as $folderId) {
            $this->removeFromTrash($folderId);
        }
    }

    /**
     * @param int[] $folderIds
     */
    public function markInTrashByIds(array $folderIds): int {
        if ($folderIds === array()) {
            return 0;
        }

        $placeholders = array();
        $params = array();
        foreach (array_values($folderIds) as $index => $folderId) {
            $key = ':folder_' . $index;
            $placeholders[] = $key;
            $params['folder_' . $index] = $folderId;
        }

        $stmt = $this->pdo->prepare(
                'UPDATE folder SET IN_TRASH = TRUE WHERE ID IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * @param int[] $folderIds
     */
    public function restoreFromTrashByIds(array $folderIds): int {
        if ($folderIds === array()) {
            return 0;
        }

        $placeholders = array();
        $params = array();
        foreach (array_values($folderIds) as $index => $folderId) {
            $key = ':folder_' . $index;
            $placeholders[] = $key;
            $params['folder_' . $index] = $folderId;
        }

        $stmt = $this->pdo->prepare(
                'UPDATE folder SET IN_TRASH = FALSE WHERE ID IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function findChildFolderIds(int $parentId): array {
        $stmt = $this->pdo->prepare('SELECT ID FROM folder WHERE PARENT_ID = :parentId');
        $stmt->execute(array('parentId' => $parentId));

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function deleteById(int $folderId): void {
        $stmt = $this->pdo->prepare('DELETE FROM folder WHERE ID = ?');
        $stmt->execute(array($folderId));
    }

    public function markPublic(int $folderId): void {
        $stmt = $this->pdo->prepare('UPDATE folder SET IS_PUBLIC = TRUE WHERE ID = ?');
        $stmt->execute(array($folderId));
    }

    public function incrementPublicCounter(int $folderId): void {
        $stmt = $this->pdo->prepare('UPDATE folder SET PUBLIC_VISITS_NUM = PUBLIC_VISITS_NUM + 1, LAST_VISITED_DATE = CURRENT_TIMESTAMP WHERE ID = ?');
        $stmt->execute(array($folderId));
    }

    private function mapFolder(array $row): Folder {
        return new Folder(
                (int) $row['ID'],
                $row['HASH'],
                (int) $row['USER_ID'],
                $row['PARENT_ID'] !== null ? (int) $row['PARENT_ID'] : null,
                $row['NAME'],
                new DateTime($row['CREATED_AT']),
                isset($row['PUBLIC_VISITS_NUM']) ? (int) $row['PUBLIC_VISITS_NUM'] : null,
                isset($row['LAST_VISITED_DATE']) ? new DateTime($row['LAST_VISITED_DATE']) : null,
                (bool) ($row['IS_PUBLIC'] ?? false)
        );
    }
}
