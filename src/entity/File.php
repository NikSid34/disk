<?php


namespace app\entity;


use app\enum\FileIndexStatus;
use app\enum\FileType;
use DateTime;


class File {
    function __construct(
            public ?int $id,
            public string $hash,
            public int $userId,
            public ?int $folderId,
            public string $name,
            public string $extension,
            public FileType $fileType,
            public DateTime $createdAt,
            public FileObject $fileObject,
            public ?FileObject $thumbnail,
            public ?int $visitsNum = null,
            public bool $isPublic = false,
            public FileIndexStatus $searchIndexStatus = FileIndexStatus::Pending,
            public ?DateTime $lastVisited = null
    ) {
    }
}
