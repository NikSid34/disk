<?php

declare(strict_types=1);

namespace app\test\support;

use PDO;
use RuntimeException;

class FakePdo extends PDO {
    /** @var array<string, FakePdoStatement[]> */
    private array $statementsBySql = [];
    private string $lastInsertId = '0';

    public function __construct() {
    }

    public function expectPrepare(string $sql, FakePdoStatement $statement): void {
        $normalizedSql = $this->normalizeSql($sql);
        $this->statementsBySql[$normalizedSql] ??= [];
        $this->statementsBySql[$normalizedSql][] = $statement;
    }

    public function prepare(string $query, array $options = []): FakePdoStatement|false {
        $normalizedSql = $this->normalizeSql($query);
        if (!isset($this->statementsBySql[$normalizedSql]) || $this->statementsBySql[$normalizedSql] === []) {
            throw new RuntimeException('Unexpected SQL: ' . $query);
        }

        return array_shift($this->statementsBySql[$normalizedSql]);
    }

    public function lastInsertId(?string $name = null): string|false {
        return $this->lastInsertId;
    }

    public function setLastInsertId(int|string $lastInsertId): void {
        $this->lastInsertId = (string) $lastInsertId;
    }

    private function normalizeSql(string $sql): string {
        return preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);
    }
}
