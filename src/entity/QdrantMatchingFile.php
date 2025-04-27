<?php


namespace app\entity;


class QdrantMatchingFile {
    function __construct(
            public string $hash,
            public float  $matchPercentage
    ) {
    }
}
