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
        $this->activityRepository->add(ActivityWithRawData::fromState($activity, ['raw' => 'data']));

        $file = RawActivityFile::from(Path::fromString($incomingFilename), 'raw-fit-bytes');

        $this->assertSame($expectedToBeDuplicate, $this->duplicateActivityScanner->isDuplicate(
            file: $file,
            sportType: $incomingSportType,
            startDateTime: $incomingStartDateTime,
        ));
    }

    public static function provideExistingActivityScenarios(): iterable
    {
        yield 'strava activity with same filename, different sport type and start date' => [
            ImportSource::STRAVA_API,
            'ride.fit',
            SportType::RIDE,
            SerializableDateTime::fromString('2023-10-10'),
            'ride.fit',
            SportType::RUN,
            SerializableDateTime::fromString('2024-01-01'),
            true,
        ];

        yield 'matching sport type and start date' => [
            ImportSource::FIT_FILE,
            'other.fit',
            SportType::RIDE,
            SerializableDateTime::fromString('2023-10-10'),
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
