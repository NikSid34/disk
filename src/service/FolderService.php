<?php


namespace app\service;


use app\entity\Breadcrumb;
use app\entity\Folder;
use app\repository\FileRepository;
use app\repository\FolderRepository;
use Throwable;


class FolderService {
    const BASE_FOLDER_NAME = 'Мои файлы';

    public function __construct(
            private readonly FolderRepository $folderRepository,
            private readonly FileRepository $fileRepository,
            private readonly FileLifecycleService $fileLifecycleService
    ) {
    }

    /**
     * @throws ServiceException
     */
    public function create(int $userId, string $name, ?string $parentHash): int {
        try {
            $parentFolderId = null;
            if ($parentHash !== null) {
                $parentFolder = $this->getFolderByHash($parentHash);

                if ($parentFolder === null) {
                    throw new ServiceException('Parent folder not found');
                }

                $parentFolderId = $parentFolder->id;
            }

            $uniqueHash = hash('sha256', $userId . ':' . $parentFolderId . ':' . $name);

            return $this->folderRepository->create($uniqueHash, $userId, $parentFolderId, $name);
        } catch (Throwable $e) {
            throw new ServiceException(message: 'Failed to create folder', previous: $e);
        }
    }

    /**
     * @throws ServiceException
     */
    public function getFolderByHash(string $folderHash): ?Folder {
        $folder = $this->folderRepository->findByHash($folderHash);
        if ($folder === null) {
            return null;
        }

        $currentUserId = UserService::getCurrentUserId();
        if ($currentUserId !== $folder->userId && !$folder->isPublic) {
            return null;
        }

        return $folder;
    }

    /**
     * @return Folder[]
     * @throws ServiceException
     */
    public function getUserFolders(int $userId, ?string $parentHash, ?string $sortBy, ?string $sortDirection, ?string $search): array {
        try {
            $parentId = $this->resolveParentFolderId($parentHash);

            return $this->folderRepository->findUserFolders(
                    $userId,
                    $parentId,
                    $this->normalizeSortField($sortBy),
                    $this->normalizeSortDirection($sortDirection),
                    $search
            );
        } catch (Throwable $e) {
            throw new ServiceException(message: 'Error while getting user folders', previous: $e);
        }
    }

    public function getUserPublicFolders(int $userId): array {
        return $this->folderRepository->findPublicByUser($userId);
    }

    /**
     * @throws ServiceException
     */
    public function getFolderName(string $hash, int $userId): string {
        $name = $this->folderRepository->findNameByHashAndUser($hash, $userId);
        if ($name === null) {
            throw new ServiceException("Folder $hash does not exist");
        }

        return $name;
    }

    /**
     * @return Breadcrumb[]
     * @throws ServiceException
     */
    public function getBreadcrumbs(string $hash, int $userId): array {
        $folder = $this->getFolderByHash($hash);
        if ($folder === null) {
            throw new ServiceException('Folder not found');
        }

        $breadcrumbs = array();
        $parentId = $folder->parentId;

        while ($parentId !== null) {
            $parent = $this->folderRepository->findByIdAndUser($parentId, $userId);
            if ($parent === null) {
                break;
            }

            array_unshift($breadcrumbs, new Breadcrumb($parent['HASH'], $parent['NAME']));
            $parentId = $parent['PARENT_ID'] !== null ? (int) $parent['PARENT_ID'] : null;
        }

        return array_merge(array(new Breadcrumb('', self::BASE_FOLDER_NAME)), $breadcrumbs);
    }

    public function getTrashedFolders(int $userId, ?string $parentFolderHash = null, ?string $sortBy = null, ?string $sortDirection = null): array {
        $parentId = $this->resolveParentFolderId($parentFolderHash);

        return $this->folderRepository->findTrashedFolders(
                $userId,
                $parentId,
                $this->normalizeSortField($sortBy),
                $this->normalizeSortDirection($sortDirection)
        );
    }

    /**
     * @throws ServiceException
     */
    public function moveToTrash(string $folderHash): void {
        $userId = UserService::getCurrentUserId();
        $folderId = $this->folderRepository->findIdByHash($folderHash, $userId, false);
        if ($folderId === null) {
            throw new ServiceException('Folder not found');
        }

        $allFolderIds = $this->collectFolderTreeIds($folderId);
        $fileIds = $this->collectFileIdsByFolderIds($allFolderIds);

        $this->folderRepository->markInTrashByIds($allFolderIds);
        $this->folderRepository->addToTrashMany($allFolderIds);
        $this->fileRepository->markInTrashByIds($fileIds);
        $this->fileRepository->addToTrashMany($fileIds);
    }

    /**
     * @throws ServiceException
     */
    public function restoreFromTrash(string $folderHash): void {
        $userId = UserService::getCurrentUserId();
        $folderId = $this->folderRepository->findIdByHash($folderHash, $userId, true);
        if ($folderId === null) {
            throw new ServiceException('Folder not found in trash');
        }

        $allFolderIds = $this->collectFolderTreeIds($folderId);
        $fileIds = $this->collectFileIdsByFolderIds($allFolderIds);

        $this->folderRepository->restoreFromTrashByIds($allFolderIds);
        $this->folderRepository->removeFromTrashMany($allFolderIds);
        $this->fileRepository->restoreFromTrashByIds($fileIds);
        $this->fileRepository->removeFromTrashMany($fileIds);
    }

    /**
     * @throws ServiceException
     */
    public function deleteForever(string $folderHash): void {
        $userId = UserService::getCurrentUserId();
        $folderId = $this->folderRepository->findIdByHash($folderHash, $userId, true);
        if ($folderId === null) {
            throw new ServiceException('Папка не найдена в корзине');
        }

        $allFolderIds = $this->collectFolderTreeIds($folderId);
        $this->fileLifecycleService->deleteFilesInFolders($allFolderIds);
        $this->folderRepository->deleteById($folderId);
    }

    public function share(string $folderHash): void {
        $folder = $this->getFolderByHash($folderHash);

        if ($folder === null) {
            throw new ServiceException('Папка не найдена');
        }

        $this->folderRepository->markPublic($folder->id);
    }

    public function increasePublicCounter(string $folderHash): void {
        $folder = $this->getFolderByHash($folderHash);
        if ($folder === null) {
            throw new ServiceException('Папка не найдена');
        }

        $this->folderRepository->incrementPublicCounter($folder->id);
    }

    private function resolveParentFolderId(?string $parentHash): ?int {
        if ($parentHash === null) {
            return null;
        }

        $parentFolder = $this->getFolderByHash($parentHash);
        if ($parentFolder === null) {
            throw new ServiceException('Folder not found');
        }

        return $parentFolder->id;
    }

    private function normalizeSortField(?string $sortBy): string {
        $sortBy = !empty($sortBy) ? strtoupper($sortBy) : 'DATE';

        return array(
                'NAME' => 'f.NAME',
                'DATE' => 'f.CREATED_AT',
                'SIZE' => 'total_size',
        )[$sortBy] ?? 'f.CREATED_AT';
    }

    private function normalizeSortDirection(?string $sortDirection): string {
        return strtoupper((string) $sortDirection) === 'ASC' ? 'ASC' : 'DESC';
    }

    /**
     * @return int[]
     */
    private function collectFolderTreeIds(int $folderId): array {
        $ids = array($folderId);

        foreach ($this->folderRepository->findChildFolderIds($folderId) as $childFolderId) {
            $ids = array_merge($ids, $this->collectFolderTreeIds($childFolderId));
        }

        return $ids;
    }

    /**
     * @param int[] $folderIds
     * @return int[]
     */
    private function collectFileIdsByFolderIds(array $folderIds): array {
        return array_map(
                static fn(array $file): int => (int) $file['id'],
                $this->fileRepository->findFilesByFolderIds($folderIds)
        );
    }
}
