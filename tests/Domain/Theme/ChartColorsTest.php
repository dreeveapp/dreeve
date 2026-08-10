<?php

declare(strict_types=1);

namespace App\Tests\Domain\Theme;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\Gears;
use App\Domain\Theme\ChartColors;
use App\Tests\Domain\Gear\GearBuilder;
use PHPUnit\Framework\TestCase;

class ChartColorsTest extends TestCase
{
    public function testItAssignsPaletteColorsInOrder(): void
    {
        $chartColors = ChartColors::for(
            SportTypes::fromArray([SportType::RIDE, SportType::RUN, SportType::HIKE]),
            Gears::empty(),
        );

        $palette = ChartColors::palette();
        $this->assertEquals($palette[0], $chartColors->forSportType(SportType::RIDE));
        $this->assertEquals($palette[1], $chartColors->forSportType(SportType::RUN));
        $this->assertEquals($palette[2], $chartColors->forSportType(SportType::HIKE));
    }

    public function testItAssignsSportTypesAndGearIndependently(): void
    {
        $gear = GearBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('1'))->build();

        $chartColors = ChartColors::for(
            SportTypes::fromArray([SportType::RIDE]),
            Gears::fromArray([$gear]),
        );

        $this->assertEquals(ChartColors::palette()[0], $chartColors->forSportType(SportType::RIDE));
        $this->assertEquals(ChartColors::palette()[0], $chartColors->forGear($gear->getId()));
    }

    public function testItWrapsAroundWhenThereAreMoreItemsThanColors(): void
    {
        $sportTypes = array_slice(SportType::cases(), 0, count(ChartColors::palette()) + 2);

        $chartColors = ChartColors::for(
            SportTypes::fromArray($sportTypes),
            Gears::empty(),
        );

        $palette = ChartColors::palette();
        $this->assertEquals($palette[0], $chartColors->forSportType($sportTypes[count($palette)]));
        $this->assertEquals($palette[1], $chartColors->forSportType($sportTypes[count($palette) + 1]));
    }

    public function testItHasNoColorForSomethingOutsideTheSet(): void
    {
        $chartColors = ChartColors::for(
            SportTypes::fromArray([SportType::RIDE]),
            Gears::empty(),
        );

        $this->assertNull($chartColors->forSportType(SportType::RUN));
        $this->assertNull($chartColors->forGear(GearId::fromUnprefixed('1')));
    }

    public function testItRoundTripsThroughItsMap(): void
    {
        $gear = GearBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('1'))->build();

        $chartColors = ChartColors::for(
            SportTypes::fromArray([SportType::RIDE, SportType::RUN]),
            Gears::fromArray([$gear]),
        );

        $this->assertEquals($chartColors->toMap(), ChartColors::fromMap($chartColors->toMap())->toMap());
    }

    public function testItTreatsAMissingMapAsEmpty(): void
    {
        $chartColors = ChartColors::fromMap([]);

        $this->assertNull($chartColors->forSportType(SportType::RIDE));
        $this->assertNull($chartColors->forGear(GearId::fromUnprefixed('1')));
    }
}
