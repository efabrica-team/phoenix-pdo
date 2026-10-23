<?php

declare(strict_types=1);

namespace Phoenix\Database\Adapter\Pdo;

use PDO;
use Phoenix\Config\EnvironmentConfig;
use Phoenix\Database\Adapter\AdapterFactory;
use Phoenix\Database\Adapter\AdapterFactoryInterface;

/**
 * Shared part of the PDO based adapter factories, see MysqlAdapterFactory and PgsqlAdapterFactory.
 */
abstract class PdoAdapterFactory implements AdapterFactoryInterface
{
    public function create(EnvironmentConfig $config): PdoAdapter
    {
        $pdo = new PDO($config->getDsn(), $config->getUsername(), $config->getPassword(), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT
        ]);

        $version = AdapterFactory::normalizeVersion($config->getVersion() ?? $pdo->getAttribute(PDO::ATTR_SERVER_VERSION));

        $pdoAdapter = $this->createAdapter($pdo, $version);
        $pdoAdapter->setCharset($config->getCharset());
        $pdoAdapter->setCollation($config->getCollation());
        return $pdoAdapter;
    }

    abstract protected function createAdapter(PDO $pdo, ?string $version): PdoAdapter;
}
