<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Shifting;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Shifting\ActivityGearUsage;
use App\Domain\Activity\Shifting\ActivityGearUsageRepository;
use App\Domain\Activity\Shifting\DbalActivityGearUsageRepository;
use App\Domain\Activity\Shifting\GearPosition;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use Spatie\Snapshots\MatchesSnapshots;

class DbalActivityGearUsageRepositoryTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private ActivityGearUsageRepository $activityGearUsageRepository;

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

    public function testFindByActivityWhenThereIsNone(): void
    {
        $this->assertTrue($this->activityGearUsageRepository->findByActivity(ActivityId::fromUnprefixed('test'))->isEmpty());
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

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityGearUsageRepository = new DbalActivityGearUsageRepository($this->getConnection());
    }
}
