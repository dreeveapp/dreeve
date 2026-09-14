<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Migrations;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;

final readonly class MigrationSquashHandler
{
    private const string SQUASHED_MIGRATION = 'DoctrineMigrations\\Version20260912000000';
    private const string LAST_MIGRATION_BEFORE_SQUASH = 'DoctrineMigrations\\Version20260911091856';

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(): void
    {
        if (!$this->migrationVersionsTableExists()) {
            // Fresh install, let migrations run normally.
            // The squashed migration will create the schema.
            return;
        }

        if ($this->hasExecutedMigration(self::SQUASHED_MIGRATION)) {
            // Squashed migration already executed, nothing to do.
            return;
        }

        if (!$this->hasExecutedMigration(self::LAST_MIGRATION_BEFORE_SQUASH)) {
            // User was not at the latest version before squash.
            // They need to update to the last version before the squash first.
            throw new MigrationsOutdated();
        }

        // User was at the latest version before squash.
        // Mark the squashed migration as executed without running it,
        // so the user doesn't notice the squash.
        $this->markMigrationAsExecuted();
    }

    private function migrationVersionsTableExists(): bool
    {
        return $this->connection->createSchemaManager()->tablesExist(['migration_versions']);
    }

    private function hasExecutedMigration(string $version): bool
    {
        $result = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM migration_versions WHERE version = :version',
            ['version' => $version]
        );

        return (int) $result > 0;
    }

    private function markMigrationAsExecuted(): void
    {
        $this->connection->transactional(function (Connection $connection): void {
            $connection->executeStatement('DELETE FROM migration_versions');
            $connection->insert('migration_versions', [
                'version' => self::SQUASHED_MIGRATION,
                'executed_at' => SerializableDateTime::fromString('now')->format('Y-m-d H:i:s'),
                'execution_time' => 0,
            ]);
        });
    }
}
