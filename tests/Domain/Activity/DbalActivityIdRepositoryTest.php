<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\DbalActivityIdRepository;
use App\Domain\Activity\DbalActivityRepository;
use App\Domain\Activity\ImportSource;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\GearType;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Infrastructure\Eventing\SpyEventBus;
use Spatie\Snapshots\MatchesSnapshots;

class DbalActivityIdRepositoryTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private ActivityIdRepository $activityIdRepository;
    private GearRepository $gearRepository;
    private ActivityRepository $activityRepository;

    public function testFindAllAndCount(): void
    {
        $activityOne = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1))
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 14:00:34'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activityOne,
            ['raw' => 'data']
        ));
        $activityTwo = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(2))
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 13:00:34'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activityTwo,
            ['raw' => 'data']
        ));
        $activityThree = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(3))
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-09 14:00:34'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activityThree,
            ['raw' => 'data']
        ));

        $this->assertEquals(
            ActivityIds::fromArray([$activityOne->getId(), $activityTwo->getId(), $activityThree->getId()]),
            $this->activityIdRepository->findAll()
        );

        $this->assertEquals(
            3,
            $this->activityIdRepository->count()
        );
    }

    public function testFindAllImportedFromStravaApi(): void
    {
        $activityOne = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1))
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 14:00:34'))
            ->withImportSource(ImportSource::STRAVA_API)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activityOne,
            ['raw' => 'data']
        ));
        $activityTwo = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(2))
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 13:00:34'))
            ->withImportSource(ImportSource::FIT_FILE)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activityTwo,
            ['raw' => 'data']
        ));
        $activityThree = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(3))
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-09 14:00:34'))
            ->withImportSource(ImportSource::STRAVA_API)
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activityThree,
            ['raw' => 'data']
        ));

        $this->assertEquals(
            ActivityIds::fromArray([$activityOne->getId(), $activityThree->getId()]),
            $this->activityIdRepository->findAllImportedFromStravaApi()
        );
    }

    public function testHasImportedFromStravaApi(): void
    {
        $this->assertFalse($this->activityIdRepository->hasImportedFromStravaApi());

        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(1))
                ->withImportSource(ImportSource::FIT_FILE)
                ->build(),
            ['raw' => 'data']
        ));

        $this->assertFalse($this->activityIdRepository->hasImportedFromStravaApi());

        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(2))
                ->withImportSource(ImportSource::STRAVA_API)
                ->build(),
            ['raw' => 'data']
        ));

        $this->assertTrue($this->activityIdRepository->hasImportedFromStravaApi());
    }

    public function testFindAllWithoutStravaGear(): void
    {
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('imported'))
                ->build()
        );
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearType(GearType::CUSTOM)
                ->withGearId(GearId::fromUnprefixed('custom'))
                ->build()
        );
        $activityOne = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(1))
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 14:00:34'))
            ->withoutGearId()
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activityOne,
            ['raw' => 'data']
        ));
        $activityTwo = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(2))
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 13:00:34'))
            ->withGearId(GearId::fromUnprefixed('imported'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activityTwo,
            ['raw' => 'data']
        ));
        $activityThree = ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed(3))
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-09 14:00:34'))
            ->withGearId(GearId::fromUnprefixed('custom'))
            ->build();
        $this->activityRepository->add(ActivityWithRawData::fromState(
            $activityThree,
            ['raw' => 'data']
        ));

        $this->assertEquals(
            ActivityIds::fromArray([$activityOne->getId(), $activityThree->getId()]),
            $this->activityIdRepository->findAllWithoutStravaGear()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityIdRepository = new DbalActivityIdRepository(
            $this->getConnection(),
        );
        $this->activityRepository = new DbalActivityRepository(
            $this->getConnection(),
            new SpyEventBus(),
        );
    }
}
