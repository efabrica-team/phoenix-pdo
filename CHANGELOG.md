# Change Log

## [Unreleased]
### Added
- added support for PHP 8.6
- shared PDO base extracted from efabrica/phoenix-core: abstract Phoenix\Database\Adapter\Pdo\PdoAdapter, PdoStatementWrapper and abstract PdoAdapterFactory (one factory per adapter, see AdapterFactoryInterface::getAdapterName())

### Changed
- MySQL and PostgreSQL adapters moved to their own packages efabrica/phoenix-mysql and efabrica/phoenix-pgsql, this package registers no adapter by itself
