<?php


namespace app\entity;


class FolderHash {
    function __construct(
            public ?int $id,
            public int $folderId,
            public string $hash
    ) {
    }
}
