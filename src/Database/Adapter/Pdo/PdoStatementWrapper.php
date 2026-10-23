<?php

declare(strict_types=1);

namespace Phoenix\Database\Adapter\Pdo;

use PDO;
use PDOStatement;
use Phoenix\Database\Adapter\StatementInterface;

/**
 * StatementInterface implementation on top of a native PDO prepared statement.
 *
 * Keeps PDOStatement out of the public adapter API; the native statement is still
 * reachable via getPdoStatement() for anything the small shared interface does not cover.
 */
final class PdoStatementWrapper implements StatementInterface
{
    private PDOStatement $statement;

    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    public function getPdoStatement(): PDOStatement
    {
        return $this->statement;
    }

    public function getQueryString(): string
    {
        return $this->statement->queryString;
    }

    public function bindValue(string $name, mixed $value): void
    {
        $this->statement->bindValue($name, $value);
    }

    public function execute(): bool
    {
        return $this->statement->execute();
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function fetchAll(): array
    {
        /** @var array<array<string, mixed>> $rows */
        $rows = $this->statement->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetch(): ?array
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->statement->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function fetchColumn(int $index = 0): mixed
    {
        return $this->statement->fetchColumn($index);
    }

    public function __toString(): string
    {
        return $this->statement->queryString;
    }
}
