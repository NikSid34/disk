<?php


namespace app\entity;


use DateTime;


class Folder {
    function __construct(
            public ?int $id,
            public string $hash,
            public int $userId,
            public ?int $parentId,
            public string $name,
            public DateTime $createdAt,
            public ?int $visitsNum = null,
            public ?DateTime $lastVisited = null,
            public bool $isPublic = false
    ) {
    }
}
