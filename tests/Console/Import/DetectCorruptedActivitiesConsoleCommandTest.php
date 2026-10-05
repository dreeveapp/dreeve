<?php

namespace App\Tests\Console\Import;

use App\Console\Import\DetectCorruptedActivitiesConsoleCommand;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamType;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Activity\WorldType;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\String\CompressedString;
use App\Tests\Console\ConsoleCommandTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DetectCorruptedActivitiesConsoleCommandTest extends ConsoleCommandTestCase
{
    private DetectCorruptedActivitiesConsoleCommand $detectCorruptedActivitiesConsoleCommand;

    public function testExecuteWithoutCorruptedData(): void
    {
        $command = $this->getCommandInApplication('app:import:detect-corrupted-activities');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertSame(
            [
                'Scanning activities...',
                'No activities with corrupted data found',
            ],
            $this->scanResultOf($commandTester),
        );
    }

    public function testExecuteWithoutDataButNegativeConfirmation(): void
    {
        $this->getConnection()->executeStatement(
            'INSERT INTO Activity (activityId, data, startDateTime, sportType, name, distance,
                                    elevation, averageSpeed, maxSpeed, movingTimeInSeconds, elapsedTimeInSeconds,
                                    totalImageCount, worldType, importSource)
                VALUES (:activityId, :data, :startDateTime, :sportType, :name, :distance,
                        :elevation, :averageSpeed, :maxSpeed, :movingTimeInSeconds, :elapsedTimeInSeconds,
                        :totalImageCount, :worldType, :importSource)',
            [
                'activityId' => 'activity-test',
                'data' => '{"name": "Ride", "distance": 42,}',
                'startDateTime' => '2026-01-06',
                'sportType' => SportType::RIDE->value,
                'name' => 'Ride',
                'distance' => 4200,
                'elevation' => 4200,
                'averageSpeed' => 4200,
                'maxSpeed' => 4200,
                'movingTimeInSeconds' => 4200,
                'elapsedTimeInSeconds' => 4200,
                'totalImageCount' => 1,
                'worldType' => WorldType::REAL_WORLD->value,
                'importSource' => ImportSource::STRAVA_API->value,
            ]
        );

        $command = $this->getCommandInApplication('app:import:detect-corrupted-activities');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['no']);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertSame(
            [
                'Scanning activities...',
                'Found 1 activities with corrupted data',
                '* Activity "Ride - 06-01-2026"',
                'Do you want to delete these activities so they can be re-imported in the next run? (yes/no) [yes]:',
                '>',
            ],
            $this->scanResultOf($commandTester),
        );
    }

    public function testExecuteWithoutDataButPositiveConfirmation(): void
    {
        $this->getConnection()->executeStatement(
            'INSERT INTO Activity (activityId, data, startDateTime, sportType, name, distance,
                                    elevation, averageSpeed, maxSpeed, movingTimeInSeconds, elapsedTimeInSeconds,
                                    totalImageCount, worldType, importSource)
                VALUES (:activityId, :data, :startDateTime, :sportType, :name, :distance,
                        :elevation, :averageSpeed, :maxSpeed, :movingTimeInSeconds, :elapsedTimeInSeconds,
                        :totalImageCount, :worldType, :importSource)',
            [
                'activityId' => 'activity-test',
                'data' => '{"name": "Ride", "distance": 42,}',
                'startDateTime' => '2026-01-06',
                'sportType' => SportType::RIDE->value,
                'name' => 'Ride',
                'distance' => 4200,
                'elevation' => 4200,
                'averageSpeed' => 4200,
                'maxSpeed' => 4200,
                'movingTimeInSeconds' => 4200,
                'elapsedTimeInSeconds' => 4200,
                'totalImageCount' => 1,
                'worldType' => WorldType::REAL_WORLD->value,
                'importSource' => ImportSource::STRAVA_API->value,
            ]
        );

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                ->build(),
            rawData: []
        ));
        $this->getConnection()->executeStatement(
            'INSERT INTO ActivityStream(activityId, streamType, createdOn, data, dataSize) 
                VALUES (:activityId, :streamType, :createdOn, :data, :dataSize)',
            [
                'activityId' => 'activity-test-2',
                'streamType' => StreamType::DISTANCE->value,
                'createdOn' => '2026-01-06',
                'data' => (string) CompressedString::fromUncompressed('{"name": "Ride", "distance": 42,}'),
                'dataSize' => 2,
            ]
        );

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-3'))
                ->build(),
            rawData: []
        ));
        $this->getConnection()->executeStatement(
            'INSERT INTO CombinedActivityStream(activityId, unitSystem, streamTypes, data, maxYAxisValue) 
                VALUES (:activityId, :unitSystem, :streamTypes, :data, :maxYAxisValue)',
            [
                'activityId' => 'activity-test-3',
                'unitSystem' => UnitSystem::METRIC->value,
                'streamTypes' => CombinedStreamType::DISTANCE->value,
                'data' => CompressedString::fromUncompressed('{"name": "Ride", "distance": 42,}'),
                'maxYAxisValue' => 4,
            ]
        );

        $command = $this->getCommandInApplication('app:import:detect-corrupted-activities');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertSame(
            [
                'Scanning activities...',
                'Found 3 activities with corrupted data',
                '* Activity "Ride - 06-01-2026"',
                '* Activity "Test activity - 10-10-2023"',
                '* Activity "Test activity - 10-10-2023"',
                'Do you want to delete these activities so they can be re-imported in the next run? (yes/no) [yes]:',
                '>',
                'Deleting activities...',
                '=> Activity "Ride - 06-01-2026" deleted',
                '=> Activity "Test activity - 10-10-2023" deleted',
            ],
            $this->scanResultOf($commandTester),
        );

        $this->assertSame(
            [
                ['objectId' => 'test', 'objectType' => 'activity', 'aspectType' => 'create', 'payload' => '[]'],
                ['objectId' => 'test-2', 'objectType' => 'activity', 'aspectType' => 'create', 'payload' => '[]'],
            ],
            $this->getConnection()->executeQuery('SELECT objectId, objectType, aspectType, payload FROM WebhookEvent')->fetchAllAssociative()
        );
    }

    public function testExecuteWithCorruptedCompressedData(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                ->build(),
            rawData: []
        ));
        $this->getConnection()->executeStatement(
            'INSERT INTO ActivityStream(activityId, streamType, createdOn, data, dataSize)
                VALUES (:activityId, :streamType, :createdOn, :data, :dataSize)',
            [
                'activityId' => 'activity-test-2',
                'streamType' => StreamType::DISTANCE->value,
                'createdOn' => '2026-01-06',
                'data' => 'this is not a valid ZSTD frame',
                'dataSize' => 2,
            ]
        );

        $command = $this->getCommandInApplication('app:import:detect-corrupted-activities');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertSame(
            [
                'Scanning activities...',
                'Found 1 activities with corrupted data',
                '* Activity "Test activity - 10-10-2023"',
                'Do you want to delete these activities so they can be re-imported in the next run? (yes/no) [yes]:',
                '>',
                'Deleting activities...',
                '=> Activity "Test activity - 10-10-2023" deleted',
            ],
            $this->scanResultOf($commandTester),
        );
    }

    public function testExecuteSkipsActivitiesNotImportedFromStravaApi(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                ->withImportSource(ImportSource::FIT_FILE)
                ->build(),
            rawData: []
        ));
        $this->getConnection()->executeStatement(
            'INSERT INTO ActivityStream(activityId, streamType, createdOn, data, dataSize)
                VALUES (:activityId, :streamType, :createdOn, :data, :dataSize)',
            [
                'activityId' => 'activity-test-2',
                'streamType' => StreamType::DISTANCE->value,
                'createdOn' => '2026-01-06',
                'data' => 'this is not a valid ZSTD frame',
                'dataSize' => 2,
            ]
        );

        $command = $this->getCommandInApplication('app:import:detect-corrupted-activities');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertSame(
            [
                'Scanning activities...',
                'No activities with corrupted data found',
            ],
            $this->scanResultOf($commandTester),
        );

        $this->assertCount(
            1,
            $this->getConnection()->executeQuery('SELECT * FROM Activity')->fetchAllAssociative()
        );
    }

    public function testExecuteWithCorruptedDerivedDataOnly(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('test-2'))
                ->build(),
            rawData: []
        ));
        $this->getConnection()->executeStatement(
            'INSERT INTO ActivityStreamMetric(activityId, streamType, metricType, data)
                VALUES (:activityId, :streamType, :metricType, :data)',
            [
                'activityId' => 'activity-test-2',
                'streamType' => StreamType::WATTS->value,
                'metricType' => ActivityStreamMetricType::NORMALIZED_POWER->value,
                'data' => 'this is not a valid ZSTD frame',
            ]
        );

        $command = $this->getCommandInApplication('app:import:detect-corrupted-activities');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertSame(
            [
                'Scanning activities...',
                'Found 1 activities with corrupted data',
                '* Activity "Test activity - 10-10-2023"',
                'Do you want to delete these activities so they can be re-imported in the next run? (yes/no) [yes]:',
                '>',
            ],
            $this->scanResultOf($commandTester),
        );

        $this->assertEmpty(
            $this->getConnection()->executeQuery('SELECT * FROM ActivityStreamMetric')->fetchAllAssociative()
        );
        $this->assertCount(
            1,
            $this->getConnection()->executeQuery('SELECT * FROM Activity')->fetchAllAssociative()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->detectCorruptedActivitiesConsoleCommand = $this->getContainer()->get(DetectCorruptedActivitiesConsoleCommand::class);
    }

    /**
     * @return list<string>
     */
    private function scanResultOf(CommandTester $commandTester): array
    {
        $display = $commandTester->getDisplay();

        return array_values(array_filter(array_map(
            trim(...),
            explode("\n", substr($display, (int) strpos($display, 'Scanning activities...'))),
        )));
    }

    protected function getConsoleCommand(): Command
    {
        return $this->detectCorruptedActivitiesConsoleCommand;
    }
}
