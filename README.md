# Phoenix PDO base

Shared PDO base for [Phoenix](https://github.com/efabrica/phoenix-core) database migration adapters: the abstract `Phoenix\Database\Adapter\Pdo\PdoAdapter`, `PdoStatementWrapper` (StatementInterface on top of PDOStatement) and the abstract `PdoAdapterFactory`.

This package registers no adapter by itself. Install one of the adapters built on it:
- MySQL: [`efabrica/phoenix-mysql`](https://github.com/efabrica/phoenix-mysql)
- PostgreSQL: [`efabrica/phoenix-pgsql`](https://github.com/efabrica/phoenix-pgsql)

```
composer require efabrica/phoenix-core efabrica/phoenix-mysql
```

## Own PDO based adapter
Extend `PdoAdapter` (implement the vendor specific parts: query builder, structure loading, escaping) and `PdoAdapterFactory` (`getAdapterName()` and `createAdapter()`), then register the factory via `Phoenix\Database\Adapter\AdapterFactory::registerFactory()` from a composer `autoload.files` bootstrap. See `efabrica/phoenix-mysql` for a complete example.

## Tests
Tests run on an in-memory SQLite database (`ext-pdo_sqlite`), no database server is needed.
