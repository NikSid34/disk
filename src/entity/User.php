<?php


namespace app\entity;


use DateTime;


class User {
    function __construct(
            public ?int $id,
            public string $login,
            public string $email,
            public string $password,
            public DateTime $lastLogin,
            public DateTime $registerDate
    ) {
    }
}
