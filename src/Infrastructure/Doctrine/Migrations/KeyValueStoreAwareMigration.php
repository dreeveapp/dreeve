<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Migrations;

use App\Infrastructure\KeyValue\KeyValueStore;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

abstract class KeyValueStoreAwareMigration extends AbstractMigration
{
    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        protected readonly KeyValueStore $keyValueStore,
    ) {
        parent::__construct($connection, $logger);
    }
}
