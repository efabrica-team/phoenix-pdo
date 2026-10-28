<?php

declare(strict_types=1);

namespace Phoenix\Tests\Helpers\Adapter;

use PDO;
use Phoenix\Database\Adapter\Pdo\PdoAdapter;
use Phoenix\Database\Element\MigrationTable;
use Phoenix\Database\QueryBuilder\QueryBuilderInterface;

/**
 * Minimal concrete PdoAdapter on top of SQLite, used to test the shared PDO logic in core
 * without a real MySQL / PostgreSQL adapter package.
 */
final class SqlitePdoAdapter extends PdoAdapter
{
    private QueryBuilderInterface $queryBuilder;

    public function __construct(PDO $pdo, QueryBuilderInterface $queryBuilder, ?string $version = null)
    {
        parent::__construct($pdo, $version);
        $this->queryBuilder = $queryBuilder;
    }

    public function getQueryBuilder(): QueryBuilderInterface
    {
        return $this->queryBuilder;
    }

    public function buildDoNotCheckForeignKeysQuery(): string
    {
        return 'PRAGMA foreign_keys = OFF;';
    }

    public function buildCheckForeignKeysQuery(): string
    {
        return 'PRAGMA foreign_keys = ON;';
    }

    protected function loadDatabase(): string
    {
        return 'main';
    }

    protected function loadTables(string $database): array
    {
        return [];
    }

    protected function loadColumns(string $database): array
    {
        return [];
    }

    protected function loadIndexes(string $database): array
    {
        return [];
    }

    protected function loadForeignKeys(string $database): array
    {
        return [];
    }

    protected function loadUniqueConstraints(string $database): array
    {
        return [];
    }

    protected function loadViews(string $database): array
    {
        return [];
    }

    protected function addColumn(MigrationTable $migrationTable, array $column): void
    {
    }

    protected function escapeString(string $string): string
    {
        return '"' . $string . '"';
    }

    protected function createRealValue($value)
    {
        if (is_array($value)) {
            return implode(',', $value);
        } elseif (is_bool($value)) {
            return $value ? 1 : 0;
        }
        return $value;
    }
}
