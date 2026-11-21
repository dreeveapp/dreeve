<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Migrations;

use App\Domain\Activity\Activity;
use App\Domain\Import\FileImportStatus;
use App\Domain\Import\StoredActivityFiles;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Psr\Log\LoggerInterface;

abstract class ActivityFileRefreshMigration extends AbstractMigration
{
    public function __construct(
        Connection $connection,
        LoggerInterface $logger,
        private readonly StoredActivityFiles $storedActivityFiles,
    ) {
        parent::__construct($connection, $logger);
    }

    /**
     * @return array<string, scalar|null>
     */
    abstract protected function columnsToRefresh(Activity $parsedActivity): array;

    public function up(Schema $schema): void
    {
        $total = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM FileImport WHERE status = :status AND activityId IS NOT NULL AND fileContents IS NOT NULL',
            ['status' => FileImportStatus::SUCCESS->value],
        );
        $processed = 0;

        $parsedActivityFiles = $this->storedActivityFiles->parse(function (string $failure) use (&$processed, $total): void {
            $this->write(sprintf('%d/%d Could not refresh %s', ++$processed, $total, $failure));
        });

        foreach ($parsedActivityFiles as $activityId => $parsedActivityFile) {
            $columns = $this->columnsToRefresh($parsedActivityFile->getActivity());
            $this->addSql(
                sprintf(
                    'UPDATE Activity SET %s WHERE activityId = :activityId',
                    implode(', ', array_map(
                        static fn (string $column): string => sprintf('%1$s = :%1$s', $column),
                        array_keys($columns),
                    )),
                ),
                [...$columns, 'activityId' => (string) $activityId],
            );
            $this->write(sprintf('%d/%d Refreshed %s', ++$processed, $total, $activityId));
        }
    }
}
