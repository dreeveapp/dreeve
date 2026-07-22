<?php

namespace App\Tests\Infrastructure\Twig;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Twig\EnumTwigExtension;
use App\Tests\ContainerTestCase;

class EnumTwigExtensionTest extends ContainerTestCase
{
    private EnumTwigExtension $enumTwigExtension;

    public function testGetSportTypeOptions(): void
    {
        $options = $this->enumTwigExtension->getSportTypeOptions();

        $this->assertCount(count(SportType::cases()), $options);
        foreach (SportType::cases() as $index => $sportType) {
            $this->assertSame($sportType->value, $options[$index]['value']);
            $this->assertNotEmpty($options[$index]['label']);
        }
    }

    public function testGetActivityTypeFrom(): void
    {
        $this->assertSame(
            ActivityType::RIDE,
            $this->enumTwigExtension->getActivityTypeFrom(ActivityType::RIDE->value)
        );
    }

    public function testGetActivityTypeFromItShouldThrowOnInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        $this->enumTwigExtension->getActivityTypeFrom('lol');
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->enumTwigExtension = $this->getContainer()->get(EnumTwigExtension::class);
    }
}
