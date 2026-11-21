<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Migrations;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Domain\Import\StoredActivityFiles;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Import\FileImportBuilder;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\DependencyFactory;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ActivityFileRefreshMigrationTest extends ContainerTestCase
{
    public function testTheMigrationFactoryOnlyBuildsRefreshMigrationsWithStoredActivityFiles(): void
    {
        /** @var DependencyFactory $dependencyFactory */
        $dependencyFactory = $this->getContainer()->get('doctrine.migrations.dependency_factory');
        $migrationFactory = $dependencyFactory->getMigrationFactory();

        $this->assertInstanceOf(
            ActivityFileRefreshMigrationStub::class,
            $migrationFactory->createVersion(ActivityFileRefreshMigrationStub::class),
        );
        $this->assertInstanceOf(
            MigrationStub::class,
            $migrationFactory->createVersion(MigrationStub::class),
        );
    }

    public function testUpRefreshesOnlyItsColumnsAndSkipsBrokenFiles(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $fileImportRepository = $this->getContainer()->get(FileImportRepository::class);
        $imports = [
            ['fit', 'morning-run.fit', (string) file_get_contents(__DIR__.'/../../../Domain/Import/FileParser/fixtures/activity.fit')],
            ['broken', 'broken.fit', 'not a fit file'],
        ];
        foreach ($imports as $i => [$unprefixedActivityId, $originalFilename, $fileContents]) {
            $activityRepository->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($unprefixedActivityId))
                    ->withName('Edited name')
                    ->withMovingTimeInSeconds(1)
                    ->withElevation(Meter::from(1))
                    ->build(),
                [],
            ));
            $fileImportRepository->add(
                FileImportBuilder::fromDefaults()
                    ->withFileImportId(FileImportId::fromUnprefixed($unprefixedActivityId))
                    ->withOriginalFilename($originalFilename)
                    ->withFileContents($fileContents)
                    ->withActivityId(ActivityId::fromUnprefixed($unprefixedActivityId))
                    ->withImportedOn(SerializableDateTime::fromString(sprintf('2026-06-04 10:%02d:00', $i)))
                    ->build()
            );
        }

        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);
        $migration = new ActivityFileRefreshMigrationStub(
            connection: $this->getConnection(),
            logger: new ConsoleLogger($output),
            storedActivityFiles: $this->getContainer()->get(StoredActivityFiles::class),
        );
        $migration->up(new Schema());
        foreach ($migration->getSql() as $query) {
            $this->getConnection()->executeStatement($query->getStatement(), $query->getParameters());
        }

        $this->assertSame(
            [
                ['activityId' => 'activity-broken', 'name' => 'Edited name', 'movingTimeInSeconds' => 1, 'elevation' => 1],
                ['activityId' => 'activity-fit', 'name' => 'Edited name', 'movingTimeInSeconds' => 10, 'elevation' => 10],
            ],
            $this->getConnection()->fetchAllAssociative('SELECT activityId, name, movingTimeInSeconds, elevation FROM Activity ORDER BY activityId'),
        );
        $display = $output->fetch();
        $this->assertStringContainsString('1/2 Refreshed activity-fit', $display);
        $this->assertStringContainsString('2/2 Could not refresh activity-broken (broken.fit): ', $display);
    }
}
