<?php


namespace app\entity;


class FileHash {
    function __construct(
            public ?int $id,
            public int $fileId,
            public string $hash
    ) {
    }
}
