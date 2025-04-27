<?php


namespace app\entity;


class Breadcrumb {
    function __construct(
            public string $hash,
            public string $name
    ) {
    }
}
