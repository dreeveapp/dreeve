<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Migrations;

use App\Domain\Import\RefreshFromFiles\ActivityFieldsToRefresh;
use Doctrine\DBAL\Connection;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

abstract class ActivityFieldRefreshMigration extends AbstractMigration
{
    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        protected readonly ActivityFieldsToRefresh $activityFieldsToRefresh,
    ) {
        parent::__construct($connection, $logger);
    }
}
