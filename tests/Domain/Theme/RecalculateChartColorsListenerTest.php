<?php

namespace App\Tests\Domain\Theme;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Theme\ChartColors;
use App\Domain\Theme\RecalculateChartColorsListener;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class RecalculateChartColorsListenerTest extends ContainerTestCase
{
    private ActivityRepository $activityRepository;
    private KeyValueStore $keyValueStore;
    private RecalculateChartColorsListener $listener;

    public function testItDoesNotRecalculateWhenNothingRelevantHappened(): void
    {
        $this->listener->recalculateChartColors();

        $this->expectException(EntityNotFound::class);
        $this->keyValueStore->find(Key::THEME);
    }

    public function testItRecalculatesWhenAnActivityWasAdded(): void
    {
        $this->addActivity('1', SportType::RIDE);

        $this->listener->recalculateChartColors();

        $this->assertEquals(
            [
                'sportType' => [SportType::RIDE->value => ChartColors::palette()[0]],
                'gear' => [],
            ],
            Json::decode((string) $this->keyValueStore->find(Key::THEME))
        );
    }

    public function testItRecalculatesOnlyOnceUntilSomethingChangesAgain(): void
    {
        $this->addActivity('1', SportType::RIDE);
        $this->listener->recalculateChartColors();

        // Nothing was published since the previous run, so this must not recalculate again.
        $this->keyValueStore->clear(Key::THEME);
        $this->listener->recalculateChartColors();

        $this->expectException(EntityNotFound::class);
        $this->keyValueStore->find(Key::THEME);
    }

    public function testItRecalculatesOnceNoMatterHowManyActivitiesWereImported(): void
    {
        $this->addActivity('1', SportType::RIDE);
        $this->addActivity('2', SportType::RUN);
        $this->addActivity('3', SportType::HIKE);

        $this->listener->recalculateChartColors();

        $this->assertEquals(
            [
                SportType::RIDE->value => ChartColors::palette()[0],
                SportType::RUN->value => ChartColors::palette()[1],
                SportType::HIKE->value => ChartColors::palette()[2],
            ],
            Json::decode((string) $this->keyValueStore->find(Key::THEME))['sportType']
        );
    }

    private function addActivity(string $activityId, SportType $sportType): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withSportType($sportType)
                ->buildAsNewlyCreated(),
            [],
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $this->listener = $this->getContainer()->get(RecalculateChartColorsListener::class);
    }
}
