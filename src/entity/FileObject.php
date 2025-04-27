<?php


namespace app\entity;


use DateTime;


class FileObject {
    function __construct(
            public ?int $id,
            public string $hash,
            public string $storagePath,
            public string $contentType,
            public int $fileSize,
            public int $refCount,
            public DateTime $createdAt
    ) {
    }
}
