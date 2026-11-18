<?php

namespace App\Tests\Domain\Activity\SportType;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\DbalSportTypeRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Media\MediaItemId;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class DbalSportTypeRepositoryTest extends ContainerTestCase
{
    public function testFindAll(): void
    {
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

        $sportTypeRepository = $this->sportTypeRepositoryFor([SportType::RUN, SportType::WALK]);

        $this->assertEquals(
            SportTypes::fromArray([SportType::RUN, SportType::WALK]),
            $sportTypeRepository->findAll(),
        );

        $sportTypeRepository = $this->sportTypeRepositoryFor([SportType::WALK, SportType::RUN]);

        $this->assertEquals(
            SportTypes::fromArray([SportType::WALK, SportType::RUN]),
            $sportTypeRepository->findAll(),
        );
    }

    public function testFindForImages(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::random())
                ->withSportType(SportType::RUN)
                ->withMediaItemIds(MediaItemId::fromUnprefixed('1'))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::random())
                ->withSportType(SportType::RUN)
                ->withMediaItemIds(MediaItemId::fromUnprefixed('2'))
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
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::random())
                ->withSportType(SportType::RIDE)
                ->withMediaItemIds(MediaItemId::fromUnprefixed('3'))
                ->build(),
            []
        ));

        $sportTypeRepository = $this->sportTypeRepositoryFor([SportType::RUN, SportType::WALK], [SportType::RIDE]);

        $this->assertEquals(
            SportTypes::fromArray([SportType::RUN]),
            $sportTypeRepository->findForImages(),
        );
    }

    /**
     * @param list<SportType> $sportTypesSortingOrder
     * @param list<SportType> $hidePhotosForSportTypes
     */
    private function sportTypeRepositoryFor(array $sportTypesSortingOrder, array $hidePhotosForSportTypes = []): DbalSportTypeRepository
    {
        $settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $settingsRepository->saveGroup(SettingsGroup::APPEARANCE, [
            'sportTypesSortingOrder' => array_map(fn (SportType $sportType): string => $sportType->value, $sportTypesSortingOrder),
            'photos' => [
                'hidePhotosForSportTypes' => array_map(fn (SportType $sportType): string => $sportType->value, $hidePhotosForSportTypes),
            ],
        ]);

        return new DbalSportTypeRepository($this->getConnection(), $settingsRepository);
    }
}
