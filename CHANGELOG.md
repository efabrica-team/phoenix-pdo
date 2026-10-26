# Change Log

## [Unreleased]

## [1.0.0] - 2026-10-26
### Added
- added support for PHP 8.6
- shared PDO base extracted from efabrica/phoenix-core: abstract Phoenix\Database\Adapter\Pdo\PdoAdapter, PdoStatementWrapper and abstract PdoAdapterFactory (one factory per adapter, see AdapterFactoryInterface::getAdapterName())

### Changed
- MySQL and PostgreSQL adapters moved to their own packages efabrica/phoenix-mysql and efabrica/phoenix-pgsql, this package registers no adapter by itself

[unreleased]: https://github.com/efabrica-team/phoenix-pdo/compare/1.0.0...HEAD
[1.0.0]: https://github.com/efabrica-team/phoenix-pdo/compare/d727aae0a498ca1a363174c2f30addea2ad722ec...1.0.0
