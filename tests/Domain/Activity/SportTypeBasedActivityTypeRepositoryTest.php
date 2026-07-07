<?php

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypes;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\DbalSportTypeRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportTypeBasedActivityTypeRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Tests\ContainerTestCase;

class SportTypeBasedActivityTypeRepositoryTest extends ContainerTestCase
{
    public function testFindAll(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::random())
                ->withSportType(SportType::WALK)
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::random())
                ->withSportType(SportType::RUN)
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::random())
                ->withSportType(SportType::RUN)
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::random())
                ->withSportType(SportType::WALK)
                ->build(),
            []
        ));

        $activityTypeRepository = new SportTypeBasedActivityTypeRepository(
            $this->sportTypeRepositoryFor([SportType::RUN, SportType::WALK])
        );

        $this->assertEquals(
            ActivityTypes::fromArray([ActivityType::RUN, ActivityType::WALK]),
            $activityTypeRepository->findAll(),
        );

        $activityTypeRepository = new SportTypeBasedActivityTypeRepository(
            $this->sportTypeRepositoryFor([SportType::WALK, SportType::RUN])
        );

        $this->assertEquals(
            ActivityTypes::fromArray([ActivityType::WALK, ActivityType::RUN]),
            $activityTypeRepository->findAll(),
        );
    }

    private function sportTypeRepositoryFor(array $sportTypesSortingOrder): DbalSportTypeRepository
    {
        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->save(SettingsGroup::APPEARANCE, [
            'sportTypesSortingOrder' => array_map(fn (SportType $sportType): string => $sportType->value, $sportTypesSortingOrder),
        ]);

        return new DbalSportTypeRepository($this->getConnection(), $settingsRepository);
    }
}
