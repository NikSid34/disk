<?php


namespace app\entity;


use app\enum\FileType;
use DateTime;


class MatchedFile extends File {
    public function __construct(
            ?int $id,
            string $hash,
            int $userId,
            ?int $folderId,
            string $name,
            string $extension,
            FileType $fileType,
            DateTime $createdAt,
            FileObject $fileObject,
            ?FileObject $thumbnail,
            ?int $visitsNum,
            bool $isPublic,
            public readonly float $matchedPercentage
    ) {
        parent::__construct(
                $id,
                $hash,
                $userId,
                $folderId,
                $name,
                $extension,
                $fileType,
                $createdAt,
                $fileObject,
                $thumbnail,
                $visitsNum,
                $isPublic
        );
    }

    public static function fromFile(File $file, float $matchedPercentage): self {
        return new self(
                $file->id,
                $file->hash,
                $file->userId,
                $file->folderId,
                $file->name,
                $file->extension,
                $file->fileType,
                $file->createdAt,
                $file->fileObject,
                $file->thumbnail,
                $file->visitsNum,
                $file->isPublic,
                $matchedPercentage
        );
    }
}
