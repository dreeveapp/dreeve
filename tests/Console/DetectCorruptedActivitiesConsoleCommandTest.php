<?php

namespace App\Tests\Console;

use App\Console\DetectCorruptedActivitiesConsoleCommand;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\CombinedStream\CombinedStreamType;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Activity\WorldType;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\String\CompressedString;
use App\Tests\Domain\Activity\ActivityBuilder;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DetectCorruptedActivitiesConsoleCommandTest extends ConsoleCommandTestCase
{
    use MatchesSnapshots;

    private DetectCorruptedActivitiesConsoleCommand $detectCorruptedActivitiesConsoleCommand;

    public function testExecuteWithoutCorruptedData(): void
    {
        $command = $this->getCommandInApplication('app:data:detect-corrupted-activities');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertMatchesSnapshot($commandTester->getDisplay(), new ConsoleOutputSnapshotDriver());
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

        $command = $this->getCommandInApplication('app:data:detect-corrupted-activities');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['no']);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertMatchesSnapshot($commandTester->getDisplay(), new ConsoleOutputSnapshotDriver());
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

        $command = $this->getCommandInApplication('app:data:detect-corrupted-activities');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertMatchesSnapshot($commandTester->getDisplay(), new ConsoleOutputSnapshotDriver());

        $this->assertMatchesJsonSnapshot(
            $this->getConnection()->executeQuery('SELECT * FROM WebhookEvent')->fetchAllAssociative()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->detectCorruptedActivitiesConsoleCommand = $this->getContainer()->get(DetectCorruptedActivitiesConsoleCommand::class);
    }

    protected function getConsoleCommand(): Command
    {
        return $this->detectCorruptedActivitiesConsoleCommand;
    }
}
