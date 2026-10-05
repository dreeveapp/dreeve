<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Shifting;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Shifting\ActivityDrivetrainUsage;
use App\Domain\Activity\Shifting\ActivityDrivetrainUsageRepository;
use App\Domain\Activity\Shifting\DbalActivityDrivetrainUsageRepository;
use App\Domain\Activity\Shifting\DrivetrainPosition;
use App\Tests\ContainerTestCase;

class DbalActivityDrivetrainUsageRepositoryTest extends ContainerTestCase
{
    private ActivityDrivetrainUsageRepository $activityDrivetrainUsageRepository;

    public function testAddAndFindByActivity(): void
    {
        $this->addDrivetrainUsageForActivity('test');
        $this->addDrivetrainUsageForActivity('other');

        $this->assertEquals(
            [
                [
                    'position' => 'front',
                    'gearNumber' => 2,
                    'teeth' => 53,
                    'timeInSeconds' => 13615,
                    'formattedTime' => '3:46:55',
                    'shiftCount' => 0,
                ],
                [
                    'position' => 'rear',
                    'gearNumber' => 4,
                    'teeth' => 19,
                    'timeInSeconds' => 351,
                    'formattedTime' => '05:51',
                    'shiftCount' => 31,
                ],
                [
                    'position' => 'rear',
                    'gearNumber' => 6,
                    'teeth' => 16,
                    'timeInSeconds' => 7219,
                    'formattedTime' => '2:00:19',
                    'shiftCount' => 124,
                ],
            ],
            $this->activityDrivetrainUsageRepository->findByActivity(ActivityId::fromUnprefixed('test'))->map(
                fn (ActivityDrivetrainUsage $drivetrainUsage): array => [
                    'position' => $drivetrainUsage->getPosition()->value,
                    'gearNumber' => $drivetrainUsage->getGearNumber(),
                    'teeth' => $drivetrainUsage->getTeeth(),
                    'timeInSeconds' => $drivetrainUsage->getTimeInSeconds(),
                    'formattedTime' => $drivetrainUsage->getFormattedTime(),
                    'shiftCount' => $drivetrainUsage->getShiftCount(),
                ]
            ),
        );
    }

    public function testFindByActivityWhenThereIsNone(): void
    {
        $this->assertTrue($this->activityDrivetrainUsageRepository->findByActivity(ActivityId::fromUnprefixed('test'))->isEmpty());
    }

    public function testDeleteForActivity(): void
    {
        $this->addDrivetrainUsageForActivity('test');
        $this->addDrivetrainUsageForActivity('other');

        $this->activityDrivetrainUsageRepository->deleteForActivity(ActivityId::fromUnprefixed('test'));

        $this->assertTrue($this->activityDrivetrainUsageRepository->findByActivity(ActivityId::fromUnprefixed('test'))->isEmpty());
        $this->assertCount(3, $this->activityDrivetrainUsageRepository->findByActivity(ActivityId::fromUnprefixed('other')));
    }

    private function addDrivetrainUsageForActivity(string $activityId): void
    {
        $gears = [
            [DrivetrainPosition::REAR, 6, 16, 7219, 124],
            [DrivetrainPosition::FRONT, 2, 53, 13615, 0],
            [DrivetrainPosition::REAR, 4, 19, 351, 31],
        ];

        foreach ($gears as [$position, $gearNumber, $teeth, $timeInSeconds, $shiftCount]) {
            $this->activityDrivetrainUsageRepository->add(
                ActivityDrivetrainUsageBuilder::fromDefaults()
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

        $this->activityDrivetrainUsageRepository = new DbalActivityDrivetrainUsageRepository($this->getConnection());
    }
}
