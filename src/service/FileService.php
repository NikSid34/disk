<?php


namespace app\service;


use app\Application;
use app\entity\File;
use app\entity\FileObject;
use app\entity\MatchedFile;
use app\enum\ThumbnailQualityLevel;
use app\repository\FileRepository;
use RuntimeException;
use Slim\Psr7\UploadedFile;
use Throwable;


class FileService {
    public function __construct(
            private readonly FolderService $folderService,
            private readonly AiService $aiService,
            private readonly QdrantService $qdrantService,
            private readonly FileRepository $fileRepository,
            private readonly ThumbnailService $thumbnailService,
            private readonly FileLifecycleService $fileLifecycleService,
            private readonly SearchIndexService $searchIndexService
    ) {
    }

    /**
     * @throws ServiceException
     */
    public function handleFileUpload(int $userId, UploadedFile $file, ?string $folderHash): int {
        $uploadedFile = $this->extractUploadMetadata($file);
        $folderId = $this->resolveFolderId($folderHash);

        try {
            $fileObject = $this->resolveFileObject($userId, $file, $uploadedFile);
            $fileHash = $this->generateFileHash($userId, $folderId, $uploadedFile['name']);
            $fileId = $this->fileRepository->createFile(
                    $fileHash,
                    $userId,
                    $folderId,
                    $fileObject->id,
                    $uploadedFile['name']
            );
        } catch (Throwable $e) {
            throw new ServiceException(message: 'Failed to upload file: ' . $e->getMessage(), previous: $e);
        }

        $storedFile = $this->getFileByHash($fileHash, false);
        if ($storedFile !== null) {
            $this->ensurePreviewAssets($storedFile);
        }

        return $fileId;
    }

    /**
     * @return File[]
     * @throws ServiceException
     */
    public function getUserFiles(
            int $userId,
            ?string $folderHash,
            ?string $sortBy,
            ?string $sortDirection,
            ?string $search,
            bool $smartSearch = false
    ): array {
        $folderId = $this->resolveFolderId($folderHash);
        $sortField = $this->normalizeSortField($sortBy);
        $direction = $this->normalizeSortDirection($sortDirection);

        if ($smartSearch && !empty($search)) {
            $matchedFiles = $this->qdrantService->getMatchedFiles($userId, $this->aiService->getTextEmbedding($search));
            if ($matchedFiles === array()) {
                return array();
            }

            $scoresByHash = array();
            $orderedHashes = array();
            foreach ($matchedFiles as $matchedFile) {
                $orderedHashes[] = $matchedFile->hash;
                $scoresByHash[$matchedFile->hash] = $matchedFile->matchPercentage;
            }

            $files = $this->fileRepository->findUserFiles($userId, $folderId, null, $orderedHashes, $sortField, $direction);
            $filesByHash = array();
            foreach ($files as $file) {
                $filesByHash[$file->hash] = $file;
            }

            $orderedFiles = array();
            foreach ($orderedHashes as $hash) {
                if (!isset($filesByHash[$hash])) {
                    continue;
                }

                $orderedFiles[] = MatchedFile::fromFile($filesByHash[$hash], $scoresByHash[$hash]);
            }

            return $orderedFiles;
        }

        if (!empty($search)) {
            return $this->searchFilesByContentOrName($userId, $folderId, $sortField, $direction, $search);
        }

        return $this->fileRepository->findUserFiles($userId, $folderId, $search, null, $sortField, $direction);
    }

    public function getUserTotalFiles(int $userId): int {
        return $this->fileRepository->countUserFiles($userId);
    }

    public function getUserUniqFiles(int $userId): int {
        return $this->fileRepository->countDistinctUserFileObjects($userId);
    }

    public function getUserPublicFiles(int $userId): array {
        return $this->fileRepository->findPublicFilesByUser($userId);
    }

    public function getTopLargestFiles(int $userId): array {
        return $this->fileRepository->findLargestFilesByUser($userId);
    }

    /**
     * @throws ServiceException
     */
    public function copyFile(int $userId, string $fileHash, string $targetFolderHash): int {
        $file = $this->fileRepository->findActiveByHashForUser($userId, $fileHash);
        if ($file === null) {
            throw new ServiceException('Файл не найден');
        }

        $targetFolder = $this->folderService->getFolderByHash($targetFolderHash);
        if ($targetFolder === null) {
            throw new ServiceException('Целевая папка не найдена');
        }

        $this->fileRepository->incrementFileObjectReference($file->fileObject->id);

        $copyHash = $this->generateFileHash($userId, $targetFolder->id, $file->name);
        $fileId = $this->fileRepository->createFile(
                $copyHash,
                $userId,
                $targetFolder->id,
                $file->fileObject->id,
                $file->name
        );

        return $fileId;
    }

    /**
     * @throws ServiceException
     */
    public function moveFile(int $userId, string $fileHash, string $targetFolderHash): void {
        $targetFolder = $this->folderService->getFolderByHash($targetFolderHash);
        if ($targetFolder === null) {
            throw new ServiceException('Целевая папка не найдена');
        }

        if (!$this->fileRepository->moveActiveFile($userId, $fileHash, $targetFolder->id)) {
            throw new ServiceException('Файл не найден или уже в корзине');
        }

        $this->fileRepository->markFilePendingIndexing($fileHash, $userId);
    }

    /**
     * @throws ServiceException
     */
    public function moveToTrash(string $fileHash): void {
        $userId = UserService::getCurrentUserId();
        $fileId = $this->fileRepository->findFileIdByHash($fileHash, $userId, false);
        if ($fileId === null) {
            throw new ServiceException('File not found');
        }

        $this->fileRepository->markInTrash($fileHash, $userId);
        $this->fileRepository->addToTrash($fileId);
    }

    /**
     * @throws ServiceException
     */
    public function restoreFromTrash(string $fileHash): void {
        $userId = UserService::getCurrentUserId();
        $fileId = $this->fileRepository->findFileIdByHash($fileHash, $userId, true);
        if ($fileId === null) {
            throw new ServiceException('File not found in trash');
        }

        $this->fileRepository->restoreFromTrash($fileHash, $userId);
        $this->fileRepository->removeFromTrash($fileId);
    }

    /**
     * @throws ServiceException
     */
    public function deleteForever(string $fileHash): void {
        $userId = UserService::getCurrentUserId();
        if ($this->fileRepository->findFileIdByHash($fileHash, $userId, true) === null) {
            throw new ServiceException('File not found in trash');
        }

        $this->fileLifecycleService->deleteTrashedFile($fileHash);
    }

    public function getTrashedFiles(
            int $userId,
            ?string $folderHash = null,
            ?string $sortBy = null,
            ?string $sortDirection = null
    ): array {
        return $this->fileRepository->findTrashedFiles(
                $userId,
                $this->resolveFolderId($folderHash),
                $this->normalizeSortField($sortBy),
                $this->normalizeSortDirection($sortDirection)
        );
    }

    /**
     * @throws ServiceException
     */
    public function getFileByHash(string $fileHash, bool $checkPerms = true): ?File {
        try {
            $file = $this->fileRepository->findByHash($fileHash);
            if ($file === null) {
                return null;
            }

            if (!$checkPerms) {
                return $file;
            }

            $currentUserId = UserService::getCurrentUserId();
            if ($currentUserId !== $file->userId && !$file->isPublic) {
                return null;
            }

            return $file;
        } catch (Throwable $e) {
            throw new ServiceException('Failed to get file by hash', previous: $e);
        }
    }

    public function share(string $fileHash): void {
        $file = $this->getFileByHash($fileHash);
        if ($file === null) {
            throw new RuntimeException('Файл не найден');
        }

        $this->fileRepository->markPublic($file->id);
    }

    public function move(string $fileHash, ?int $targetFolderId = null): void {
        $file = $this->getFileByHash($fileHash);
        if ($file === null) {
            throw new RuntimeException('Файл не найден');
        }

        if ($targetFolderId === null) {
            throw new RuntimeException('Целевая папка не указана');
        }

        $this->fileRepository->moveActiveFile($file->userId, $fileHash, $targetFolderId);
        $this->fileRepository->markFilePendingIndexing($fileHash, $file->userId);
    }

    public function copy(string $fileHash, ?int $targetFolderId = null): void {
        $file = $this->getFileByHash($fileHash);
        if ($file === null) {
            throw new RuntimeException('Файл не найден');
        }

        if ($targetFolderId === null) {
            $targetFolderId = $file->folderId;
        }

        $this->fileRepository->incrementFileObjectReference($file->fileObject->id);
        $copyHash = $this->generateFileHash($file->userId, $targetFolderId, $file->name);
        $this->fileRepository->createFile(
                $copyHash,
                $file->userId,
                $targetFolderId,
                $file->fileObject->id,
                $file->name
        );
    }

    public function increasePublicCounter(string $fileHash): void {
        $file = $this->getFileByHash($fileHash);
        if ($file === null) {
            throw new ServiceException('Файл не найден');
        }

        $this->fileRepository->incrementPublicCounter($file->id);
    }

    private function extractUploadMetadata(UploadedFile $file): array {
        $fileName = (string) $file->getClientFilename();
        /** @psalm-suppress InternalMethod */
        $filePath = $file->getFilePath();
        if (!is_file($filePath)) {
            throw new RuntimeException('Uploaded file path is invalid');
        }

        return array(
                'name'      => $fileName,
                'path'      => $filePath,
                'size'      => (int) $file->getSize(),
                'mimeType'  => (string) mime_content_type($filePath),
                'hash'      => hash_file('sha256', $filePath),
                'extension' => strtolower(pathinfo($fileName, PATHINFO_EXTENSION)),
        );
    }

    private function resolveFileObject(int $userId, UploadedFile $file, array $uploadedFile): FileObject {
        $existingFileObject = $this->fileRepository->findFileObjectByHash($uploadedFile['hash']);
        if ($existingFileObject !== null) {
            $this->fileRepository->incrementFileObjectReference($existingFileObject->id);
            $fileObject = $this->fileRepository->findFileObjectById($existingFileObject->id);
            if ($fileObject === null) {
                throw new RuntimeException('Existing file object disappeared');
            }

            return $fileObject;
        }

        $storagePath = $this->moveUploadedFileToStorage($userId, $file, $uploadedFile['hash'], $uploadedFile['extension']);

        return $this->fileRepository->createFileObject(
                $uploadedFile['hash'],
                $storagePath,
                $uploadedFile['mimeType'],
                $uploadedFile['size']
        );
    }

    private function moveUploadedFileToStorage(int $userId, UploadedFile $file, string $fileHash, string $extension): string {
        $basePath = Application::getPathForUpload($userId);
        if (!is_dir($basePath) && !@mkdir($basePath, 0777, true) && !is_dir($basePath)) {
            throw new RuntimeException('Failed to create storage directory');
        }

        $storageFilename = $fileHash . ($extension !== '' ? '.' . $extension : '');
        $storagePath = $basePath . '/' . $storageFilename;
        $file->moveTo($storagePath);

        return $storagePath;
    }

    private function resolveFolderId(?string $folderHash): ?int {
        if ($folderHash === null) {
            return null;
        }

        $folder = $this->folderService->getFolderByHash($folderHash);
        if ($folder === null) {
            throw new ServiceException('Folder not found');
        }

        return $folder->id;
    }

    private function generateFileHash(int $userId, ?int $folderId, string $fileName): string {
        return hash(
                'sha256',
                implode(':', array(
                        $userId,
                        $folderId,
                        $fileName,
                        microtime(true),
                        random_int(1, PHP_INT_MAX),
                ))
        );
    }

    private function ensurePreviewAssets(File $file): void {
        if ($this->thumbnailService->isPreviewable($file)) {
            $this->thumbnailService->ensureThumbnail($file, ThumbnailQualityLevel::Low);
            $this->thumbnailService->ensureThumbnail($file, ThumbnailQualityLevel::Medium);
        }
    }

    private function normalizeSortField(?string $sortBy): string {
        $sortBy = !empty($sortBy) ? strtoupper($sortBy) : 'DATE';

        return array(
                'NAME' => 'f.NAME',
                'DATE' => 'f.CREATED_AT',
                'SIZE' => 'fo.FILE_SIZE',
        )[$sortBy] ?? 'f.CREATED_AT';
    }

    private function normalizeSortDirection(?string $sortDirection): string {
        return strtoupper((string) $sortDirection) === 'ASC' ? 'ASC' : 'DESC';
    }

    /**
     * @return File[]
     */
    private function searchFilesByContentOrName(
            int $userId,
            ?int $folderId,
            string $sortField,
            string $direction,
            string $search
    ): array {
        try {
            $searchHashes = $this->searchIndexService->searchFileHashes($userId, $folderId, $search);
            $nameMatchedFiles = $this->fileRepository->findUserFiles($userId, $folderId, $search, null, $sortField, $direction);
            $combinedHashes = $searchHashes;

            foreach ($nameMatchedFiles as $file) {
                $combinedHashes[] = $file->hash;
            }

            $combinedHashes = array_values(array_unique($combinedHashes));
            if ($combinedHashes === array()) {
                return array();
            }

            return $this->fileRepository->findUserFiles($userId, $folderId, null, $combinedHashes, $sortField, $direction);
        } catch (Throwable) {
            return $this->fileRepository->findUserFiles($userId, $folderId, $search, null, $sortField, $direction);
        }
    }

}
