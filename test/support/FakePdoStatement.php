<?php

declare(strict_types=1);

namespace app\test\support;

use PDO;
use PDOStatement;

class FakePdoStatement extends PDOStatement {
    /** @var array<int, array<mixed>> */
    public array $executedParams = [];

    /** @var array<int, mixed> */
    private array $fetchQueue = [];

    /** @var array<int, array> */
    private array $fetchAllQueue = [];

    /** @var array<int, mixed> */
    private array $fetchColumnQueue = [];

    private int $rowCountValue = 0;
    private ?\Closure $onExecute = null;

    protected function __construct() {
    }

    public static function create(): self {
        return new self();
    }

    public function onExecute(callable $callback): self {
        $this->onExecute = $callback(...);

        return $this;
    }

    public function queueFetch(mixed $value): self {
        $this->fetchQueue[] = $value;

        return $this;
    }

    public function queueFetchAll(array $value): self {
        $this->fetchAllQueue[] = $value;

        return $this;
    }

    public function queueFetchColumn(mixed $value): self {
        $this->fetchColumnQueue[] = $value;

        return $this;
    }

    public function setRowCount(int $rowCount): self {
        $this->rowCountValue = $rowCount;

        return $this;
    }

    public function execute(?array $params = null): bool {
        $this->executedParams[] = $params ?? [];

        if ($this->onExecute !== null) {
            ($this->onExecute)($params ?? [], $this);
        }

        return true;
    }

    public function fetch(
            int $mode = PDO::FETCH_DEFAULT,
            int $cursorOrientation = PDO::FETCH_ORI_NEXT,
            int $cursorOffset = 0
    ): mixed {
        return $this->fetchQueue !== [] ? array_shift($this->fetchQueue) : false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        return $this->fetchAllQueue !== [] ? array_shift($this->fetchAllQueue) : [];
    }

    public function fetchColumn(int $column = 0): mixed {
        return $this->fetchColumnQueue !== [] ? array_shift($this->fetchColumnQueue) : false;
    }

    public function rowCount(): int {
        return $this->rowCountValue;
    }
}
