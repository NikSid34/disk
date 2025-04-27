<?php


namespace app\service;


use app\repository\FileRepository;
use RuntimeException;
use Throwable;


class FileLifecycleService {
    public function __construct(
            private readonly FileRepository $fileRepository,
            private readonly ThumbnailService $thumbnailService,
            private readonly ?SearchIndexService $searchIndexService = null
    ) {
    }

    /**
     * @throws ServiceException
     */
    public function deleteTrashedFile(string $fileHash): void {
        $fileRecord = $this->fileRepository->findTrashedFileRecord($fileHash);
        if ($fileRecord === null) {
            throw new ServiceException('Файл не найден в корзине');
        }

        $this->deleteFileRecord($fileRecord['id'], $fileRecord['fileObjectId'], $fileRecord['hash'] ?? null);
    }

    public function deleteFilesInFolders(array $folderIds): void {
        $files = $this->fileRepository->findFilesByFolderIds($folderIds);

        foreach ($files as $file) {
            $this->deleteFileRecord($file['id'], $file['fileObjectId'], $file['hash'] ?? null);
        }
    }

    public function deleteFileRecord(int $fileId, int $fileObjectId, ?string $fileHash = null): void {
        $fileObject = $this->fileRepository->findFileObjectById($fileObjectId);
        if ($fileObject === null) {
            throw new RuntimeException('File object not found');
        }

        $this->fileRepository->deleteFileById($fileId);
        $this->fileRepository->decrementFileObjectReference($fileObjectId);

        $updatedFileObject = $this->fileRepository->findFileObjectById($fileObjectId);
        if ($updatedFileObject !== null && $updatedFileObject->refCount > 0) {
            return;
        }

        $storageDir = dirname($fileObject->storagePath);

        if (is_file($fileObject->storagePath)) {
            unlink($fileObject->storagePath);
        }

        $this->thumbnailService->deleteCachedThumbnails($fileObject->hash, $storageDir);
        $this->fileRepository->deleteFileObjectById($fileObjectId);

        if ($fileHash !== null && $this->searchIndexService !== null) {
            try {
                $this->searchIndexService->deleteFile($fileHash);
            } catch (Throwable) {
                // Search index cleanup must not block file deletion.
            }
        }
    }
}
