<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Shifting;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Shifting\ActivityGearUsage;
use App\Domain\Activity\Shifting\ActivityGearUsageRepository;
use App\Domain\Activity\Shifting\DbalActivityGearUsageRepository;
use App\Domain\Activity\Shifting\GearPosition;
use App\Domain\Import\DbalFileImportRepository;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportIds;
use App\Domain\Import\FileImportRepository;
use App\Domain\Import\FileImportStatus;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Import\FileImportBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class DbalActivityGearUsageRepositoryTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private ActivityGearUsageRepository $activityGearUsageRepository;
    private FileImportRepository $fileImportRepository;

    public function testAddAndFindByActivity(): void
    {
        $this->addGearUsageForActivity('test');
        $this->addGearUsageForActivity('other');

        $this->assertMatchesJsonSnapshot(Json::encode(
            $this->activityGearUsageRepository->findByActivity(ActivityId::fromUnprefixed('test'))->map(
                fn (ActivityGearUsage $gearUsage): array => [
                    'position' => $gearUsage->getPosition()->value,
                    'gearNumber' => $gearUsage->getGearNumber(),
                    'teeth' => $gearUsage->getTeeth(),
                    'timeInSeconds' => $gearUsage->getTimeInSeconds(),
                    'formattedTime' => $gearUsage->getFormattedTime(),
                    'shiftCount' => $gearUsage->getShiftCount(),
                ]
            )
        ));
    }

    public function testFindByActivityHidesTheProcessedMarker(): void
    {
        $this->activityGearUsageRepository->add(ActivityGearUsage::none(ActivityId::fromUnprefixed('test')));

        $this->assertTrue($this->activityGearUsageRepository->findByActivity(ActivityId::fromUnprefixed('test'))->isEmpty());
        $this->assertSame(
            1,
            (int) $this->getConnection()->executeQuery('SELECT COUNT(*) FROM ActivityGearUsage')->fetchOne()
        );
    }

    public function testFindFileImportIdsThatNeedShiftingExtraction(): void
    {
        $this->addFileImport('needs-extraction', ImportSource::FIT_FILE, FileImportStatus::SUCCESS, 'contents');
        $this->addFileImport('already-extracted', ImportSource::FIT_FILE, FileImportStatus::SUCCESS, 'contents');
        $this->addFileImport('without-gear-changes', ImportSource::FIT_FILE, FileImportStatus::SUCCESS, 'contents');
        $this->addFileImport('failed', ImportSource::FIT_FILE, FileImportStatus::FAILED, 'contents');
        $this->addFileImport('without-contents', ImportSource::FIT_FILE, FileImportStatus::SUCCESS, null);
        $this->addFileImport('gpx', ImportSource::GPX_FILE, FileImportStatus::SUCCESS, 'contents');

        $this->addGearUsageForActivity('already-extracted');
        $this->activityGearUsageRepository->add(ActivityGearUsage::none(ActivityId::fromUnprefixed('without-gear-changes')));

        $this->assertEquals(
            FileImportIds::fromArray([FileImportId::fromUnprefixed('needs-extraction')]),
            $this->activityGearUsageRepository->findFileImportIdsThatNeedShiftingExtraction()
        );
    }

    public function testDeleteForActivity(): void
    {
        $this->addGearUsageForActivity('test');
        $this->addGearUsageForActivity('other');

        $this->activityGearUsageRepository->deleteForActivity(ActivityId::fromUnprefixed('test'));

        $this->assertTrue($this->activityGearUsageRepository->findByActivity(ActivityId::fromUnprefixed('test'))->isEmpty());
        $this->assertCount(3, $this->activityGearUsageRepository->findByActivity(ActivityId::fromUnprefixed('other')));
    }

    private function addGearUsageForActivity(string $activityId): void
    {
        $gears = [
            [GearPosition::REAR, 6, 16, 7219, 124],
            [GearPosition::FRONT, 2, 53, 13615, 0],
            [GearPosition::REAR, 4, 19, 351, 31],
        ];

        foreach ($gears as [$position, $gearNumber, $teeth, $timeInSeconds, $shiftCount]) {
            $this->activityGearUsageRepository->add(
                ActivityGearUsageBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withGear($position, $gearNumber, $teeth)
                    ->withTimeInSeconds($timeInSeconds)
                    ->withShiftCount($shiftCount)
                    ->build()
            );
        }
    }

    private function addFileImport(string $activityId, ImportSource $source, FileImportStatus $status, ?string $fileContents): void
    {
        $this->fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed($activityId))
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withSource($source)
                ->withStatus($status)
                ->withFileContents($fileContents)
                ->build()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityGearUsageRepository = new DbalActivityGearUsageRepository($this->getConnection());
        $this->fileImportRepository = new DbalFileImportRepository($this->getConnection());
    }
}
