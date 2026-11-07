<?php

namespace App\Tests\Infrastructure\Twig;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Twig\ActivityTypeTwigExtension;
use App\Tests\ContainerTestCase;

class ActivityTypeTwigExtensionTest extends ContainerTestCase
{
    private ActivityTypeTwigExtension $activityTypeTwigExtension;

    public function testGetSportTypeOptions(): void
    {
        $options = $this->activityTypeTwigExtension->getSportTypeOptions();

        $this->assertCount(count(SportType::cases()), $options);
        foreach (SportType::cases() as $index => $sportType) {
            $this->assertSame($sportType->value, $options[$index]['value']);
            $this->assertNotEmpty($options[$index]['label']);
        }
    }

    public function testGroupSportTypesByActivityType(): void
    {
        $this->assertSame(
            [
                ['activityType' => ActivityType::WALK, 'sportTypes' => [SportType::WALK, SportType::HIKE]],
                ['activityType' => ActivityType::RUN, 'sportTypes' => [SportType::RUN, SportType::TRAIL_RUN]],
            ],
            $this->activityTypeTwigExtension->groupSportTypesByActivityType([SportType::WALK, SportType::RUN, SportType::HIKE, SportType::TRAIL_RUN]),
        );
    }

    public function testGetActivityTypeFrom(): void
    {
        $this->assertSame(
            ActivityType::RIDE,
            $this->activityTypeTwigExtension->getActivityTypeFrom(ActivityType::RIDE->value)
        );
    }

    public function testGetActivityTypeFromItShouldThrowOnInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        $this->activityTypeTwigExtension->getActivityTypeFrom('lol');
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityTypeTwigExtension = $this->getContainer()->get(ActivityTypeTwigExtension::class);
    }
}
