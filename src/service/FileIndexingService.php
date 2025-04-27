<?php


namespace app\service;


use app\entity\File;
use app\enum\FileType;
use app\repository\FileRepository;
use Throwable;


class FileIndexingService {
    public function __construct(
            private readonly FileRepository $fileRepository,
            private readonly AiService $aiService,
            private readonly QdrantService $qdrantService,
            private readonly SearchIndexService $searchIndexService
    ) {
    }

    /**
     * @return array{claimed: int, indexed: int, failed: int, requeued: int}
     */
    public function processPendingFiles(int $limit = 25): array {
        $files = $this->fileRepository->claimFilesForIndexing($limit);
        $summary = array(
                'claimed' => count($files),
                'indexed' => 0,
                'failed' => 0,
                'requeued' => 0,
        );

        foreach ($files as $file) {
            try {
                $this->indexFile($file);

                if ($this->fileRepository->markFileIndexed((int) $file->id)) {
                    $summary['indexed']++;
                } else {
                    $summary['requeued']++;
                }
            } catch (Throwable) {
                $this->fileRepository->releaseFileIndexing((int) $file->id);
                $summary['failed']++;
            }
        }

        return $summary;
    }

    /**
     * @throws ServiceException
     */
    public function indexFile(File $file): void {
        if ($file->id === null) {
            throw new ServiceException('File id is required for indexing');
        }

        $errors = [];
        try {
            $this->searchIndexService->indexFile($file);
        } catch (ServiceException $e) {
            $errors[] = $e->getMessage();
        }

        try {
            $description = $this->buildSemanticDescription($file);
            if ($description === null) {
                return;
            }

            $embedding = $this->aiService->getTextEmbedding($description);
            $this->qdrantService->saveEmbedding($file->userId, $file->id, $file->hash, $embedding);
        } catch (ServiceException $e) {
            $errors[] = $e->getMessage();
        }

        if (!empty($errors)) {
            throw new ServiceException(\implode('; ', $errors));
        }
    }

    /**
     * @throws ServiceException
     */
    private function buildSemanticDescription(File $file): ?string {
        return match ($file->fileType) {
            FileType::Image => $this->aiService->getImageDescription($file),
            FileType::Word, FileType::Pdf, FileType::Text => $this->aiService->getDocumentDescription($file),
            default => null,
        };
    }
}
