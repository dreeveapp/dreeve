<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class MigrationsAreUpToDateTest extends KernelTestCase
{
    public function testSchemaFromMigrationsMatchesOrmMapping(): void
    {
        $kernel = self::bootKernel();

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        // Start from a completely empty database.
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropDatabase();

        // Run every exported migration against it.
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $exitCode = $application->run(
            new ArrayInput([
                'command' => 'doctrine:migrations:migrate',
                'version' => 'latest',
                '--no-interaction' => true,
                '--allow-no-migration' => true,
            ]),
            $output = new BufferedOutput()
        );
        $this->assertSame(0, $exitCode, 'Running the migrations failed: '.$output->fetch());

        // The migration bookkeeping table is not part of the ORM mapping.
        $connection->executeStatement('DROP TABLE IF EXISTS migration_versions');

        $migratedColumns = $this->columnsPerTable(
            $connection->createSchemaManager()->introspectSchema()
        );
        $mappedColumns = $this->columnsPerTable(
            $schemaTool->getSchemaFromMetadata(
                $entityManager->getMetadataFactory()->getAllMetadata()
            )
        );

        $differences = [];

        foreach ($mappedColumns as $table => $columns) {
            if (!isset($migratedColumns[$table])) {
                $differences[] = "Missing table: $table";
                continue;
            }
            foreach ($columns as $name => $mapped) {
                if (!isset($migratedColumns[$table][$name])) {
                    $differences[] = "$table.$name: missing column";
                    continue;
                }
                $differences = [...$differences, ...$this->compareColumn($table, $name, $migratedColumns[$table][$name], $mapped)];
            }
        }
        foreach ($migratedColumns as $table => $columns) {
            if (!isset($mappedColumns[$table])) {
                $differences[] = "Table not in mapping: $table";
                continue;
            }
            foreach ($columns as $name => $migrated) {
                if (!isset($mappedColumns[$table][$name])) {
                    $differences[] = "$table.$name: column not in mapping";
                }
            }
        }

        $this->assertSame(
            [],
            $differences,
            "The schema built from ./migrations is out of sync with the ORM mapping.\n".
            "A migration is probably missing; generate it with `make migrate-doff`.\n".
            "Differences:\n - ".implode("\n - ", $differences)
        );
    }

    private function compareColumn(string $table, string $name, array $migrated, array $mapped): array
    {
        $differences = [];
        foreach (['notnull', 'default'] as $property) {
            $migratedValue = match (true) {
                null === $migrated[$property] => 'NULL',
                is_bool($migrated[$property]) => $migrated[$property] ? 'true' : 'false',
                default => (string) $migrated[$property],
            };
            $mappedValue = match (true) {
                null === $mapped[$property] => 'NULL',
                is_bool($mapped[$property]) => $mapped[$property] ? 'true' : 'false',
                default => (string) $mapped[$property],
            };
            if ($migratedValue !== $mappedValue) {
                $differences[] = sprintf(
                    '%s.%s: %s %s -> %s',
                    $table,
                    $name,
                    $property,
                    $migratedValue,
                    $mappedValue,
                );
            }
        }

        return $differences;
    }

    private function columnsPerTable(Schema $schema): array
    {
        $result = [];
        foreach ($schema->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                $result[$table->getObjectName()->toString()][$column->getObjectName()->toString()] = [
                    'notnull' => $column->getNotnull(),
                    'default' => $column->getDefault(),
                ];
            }
        }

        return $result;
    }
}
