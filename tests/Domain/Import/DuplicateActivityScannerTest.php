<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\DbalActivityRepository;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Import\DuplicateActivityScanner;
use App\Domain\Import\FileParser\RawActivityFile;
use App\Infrastructure\ValueObject\String\ExternalReferenceId;
use App\Infrastructure\ValueObject\String\Path;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class DuplicateActivityScannerTest extends ContainerTestCase
{
    private DuplicateActivityScanner $duplicateActivityScanner;
    private ActivityRepository $activityRepository;

    #[DataProvider('provideExistingActivityScenarios')]
    public function testItDetectsDuplicatesAgainstAnExistingActivity(
        ImportSource $storedImportSource,
        string $storedFilename,
        SportType $storedSportType,
        SerializableDateTime $storedStartDateTime,
        array $storedRawData,
        string $incomingFilename,
        SportType $incomingSportType,
        SerializableDateTime $incomingStartDateTime,
        bool $expectedToBeDuplicate,
    ): void {
        $activity = ActivityBuilder::fromDefaults()
            ->withImportSource($storedImportSource)
            ->withExternalReferenceId(ExternalReferenceId::fromString($storedFilename))
            ->withSportType($storedSportType)
            ->withStartDateTime($storedStartDateTime)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, $storedRawData));

        $file = RawActivityFile::from(Path::fromString($incomingFilename), 'raw-fit-bytes');

        $this->assertSame($expectedToBeDuplicate, $this->duplicateActivityScanner->isDuplicate(
            file: $file,
            sportType: $incomingSportType,
            startDateTime: $incomingStartDateTime,
        ));
    }

    public static function provideExistingActivityScenarios(): iterable
    {
        yield 'strava activity with same filename and same UTC start instant' => [
            ImportSource::STRAVA_API,
            'ride.fit',
            SportType::RIDE,
            SerializableDateTime::fromString('2023-10-10'),
            ['start_date' => '2024-01-01T10:00:00Z'],
            'ride.fit',
            SportType::RUN,
            SerializableDateTime::fromString('2024-01-01 10:00:00', new \DateTimeZone('UTC')),
            true,
        ];

        yield 'strava activity with same filename but different start instant' => [
            ImportSource::STRAVA_API,
            'ride.fit',
            SportType::RIDE,
            SerializableDateTime::fromString('2023-10-10'),
            ['start_date' => '2023-10-10T08:00:00Z'],
            'ride.fit',
            SportType::RUN,
            SerializableDateTime::fromString('2024-01-01 10:00:00', new \DateTimeZone('UTC')),
            false,
        ];

        yield 'strava activity with same filename and same instant in another timezone' => [
            ImportSource::STRAVA_API,
            'ride.fit',
            SportType::RIDE,
            SerializableDateTime::fromString('2023-10-10'),
            ['start_date' => '2024-01-01T10:00:00Z'],
            'ride.fit',
            SportType::RUN,
            SerializableDateTime::fromString('2024-01-01 11:00:00', new \DateTimeZone('Europe/Brussels')),
            true,
        ];

        yield 'strava activity with same filename and start instant within tolerance' => [
            ImportSource::STRAVA_API,
            'ride.fit',
            SportType::RIDE,
            SerializableDateTime::fromString('2023-10-10'),
            ['start_date' => '2024-01-01T10:00:30Z'],
            'ride.fit',
            SportType::RUN,
            SerializableDateTime::fromString('2024-01-01 10:00:00', new \DateTimeZone('UTC')),
            true,
        ];

        yield 'strava activity with same filename and start instant outside tolerance' => [
            ImportSource::STRAVA_API,
            'ride.fit',
            SportType::RIDE,
            SerializableDateTime::fromString('2023-10-10'),
            ['start_date' => '2024-01-01T10:05:00Z'],
            'ride.fit',
            SportType::RUN,
            SerializableDateTime::fromString('2024-01-01 10:00:00', new \DateTimeZone('UTC')),
            false,
        ];

        yield 'strava activity with same filename but no start_date in raw data' => [
            ImportSource::STRAVA_API,
            'ride.fit',
            SportType::RIDE,
            SerializableDateTime::fromString('2023-10-10'),
            ['raw' => 'data'],
            'ride.fit',
            SportType::RUN,
            SerializableDateTime::fromString('2024-01-01 10:00:00', new \DateTimeZone('UTC')),
            false,
        ];

        yield 'matching sport type and start date' => [
            ImportSource::FIT_FILE,
            'other.fit',
            SportType::RIDE,
            SerializableDateTime::fromString('2023-10-10'),
            ['raw' => 'data'],
            'ride.fit',
            SportType::RIDE,
            SerializableDateTime::fromString('2023-10-10'),
            true,
        ];

        yield 'matching start date but different sport type' => [
            ImportSource::FIT_FILE,
            'other.fit',
            SportType::RIDE,
            SerializableDateTime::fromString('2023-10-10'),
            ['raw' => 'data'],
            'ride.fit',
            SportType::RUN,
            SerializableDateTime::fromString('2023-10-10'),
            false,
        ];

        yield 'matching sport type but different start date' => [
            ImportSource::FIT_FILE,
            'other.fit',
            SportType::RIDE,
            SerializableDateTime::fromString('2023-10-10'),
            ['raw' => 'data'],
            'ride.fit',
            SportType::RIDE,
            SerializableDateTime::fromString('2024-01-01'),
            false,
        ];

        yield 'filename only matches a file-imported activity (not strava)' => [
            ImportSource::FIT_FILE,
            'ride.fit',
            SportType::RIDE,
            SerializableDateTime::fromString('2023-10-10'),
            ['raw' => 'data'],
            'ride.fit',
            SportType::RUN,
            SerializableDateTime::fromString('2024-01-01'),
            false,
        ];
    }

    public function testItIsNotDuplicateWhenNothingMatches(): void
    {
        $file = RawActivityFile::from(Path::fromString('ride.fit'), 'raw-fit-bytes');

        $this->assertFalse($this->duplicateActivityScanner->isDuplicate(
            file: $file,
            sportType: SportType::RIDE,
            startDateTime: SerializableDateTime::fromString('2023-10-10'),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = new DbalActivityRepository(
            $this->getConnection(),
        );
        $this->duplicateActivityScanner = new DuplicateActivityScanner(
            $this->getConnection(),
        );
    }
}
