<?php

declare(strict_types=1);

namespace App\Domain\Milestone\FunComparison;

use App\Infrastructure\Measurement\Length\Kilometer;
use Symfony\Contracts\Translation\TranslatorInterface;

enum DistanceFunComparison: string implements FunComparison
{
    case EDGE_OF_SPACE = 'distanceEdgeOfSpace';
    case LENGTH_OF_JAMAICA = 'distanceLengthOfJamaica';
    case MADRID_TO_BARCELONA = 'distanceMadridToBarcelona';
    case FRANCE_LENGTH = 'distanceFranceLength';
    case DANUBE_RIVER = 'distanceDanubeRiver';
    case GREAT_WALL_OF_CHINA = 'distanceGreatWallOfChina';
    case QUARTER_AROUND_EARTH = 'distanceQuarterAroundEarth';
    case LONDON_TO_PERTH = 'distanceLondonToPerth';
    case HALFWAY_AROUND_EARTH = 'distanceHalfwayAroundEarth';
    case CIRCUMFERENCE_OF_MARS = 'distanceCircumferenceOfMars';
    case THREE_QUARTERS_AROUND_EARTH = 'distanceThreeQuartersAroundEarth';
    case EARTH_CIRCUMFERENCE = 'distanceEarthCircumference';
    case DIAMETER_OF_NEPTUNE = 'distanceDiameterOfNeptune';
    case NEARLY_TWICE_AROUND_EARTH = 'distanceNearlyTwiceAroundEarth';
    case TWO_AND_HALF_TIMES_AROUND_EARTH = 'distanceTwoAndHalfTimesAroundEarth';
    case DIAMETER_OF_JUPITER = 'distanceDiameterOfJupiter';
    case FIVE_TIMES_AROUND_EARTH = 'distanceFiveTimesAroundEarth';
    case SPEED_OF_LIGHT_PER_SECOND = 'distanceSpeedOfLightPerSecond';
    case EARTH_TO_MOON = 'distanceEarthToMoon';
    case MORE_THAN_EARTH_TO_MOON = 'distanceMoreThanEarthToMoon';

    public static function resolve(Kilometer $distance): ?self
    {
        $km = $distance->toFloat();

        return match (true) {
            $km >= 500_000 => self::MORE_THAN_EARTH_TO_MOON,
            $km >= 400_000 => self::EARTH_TO_MOON,
            $km >= 300_000 => self::SPEED_OF_LIGHT_PER_SECOND,
            $km >= 200_000 => self::FIVE_TIMES_AROUND_EARTH,
            $km >= 150_000 => self::DIAMETER_OF_JUPITER,
            $km >= 100_000 => self::TWO_AND_HALF_TIMES_AROUND_EARTH,
            $km >= 75_000 => self::NEARLY_TWICE_AROUND_EARTH,
            $km >= 50_000 => self::DIAMETER_OF_NEPTUNE,
            $km >= 40_000 => self::EARTH_CIRCUMFERENCE,
            $km >= 30_000 => self::THREE_QUARTERS_AROUND_EARTH,
            $km >= 25_000 => self::CIRCUMFERENCE_OF_MARS,
            $km >= 20_000 => self::HALFWAY_AROUND_EARTH,
            $km >= 15_000 => self::LONDON_TO_PERTH,
            $km >= 10_000 => self::QUARTER_AROUND_EARTH,
            $km >= 5_000 => self::GREAT_WALL_OF_CHINA,
            $km >= 2_500 => self::DANUBE_RIVER,
            $km >= 1_000 => self::FRANCE_LENGTH,
            $km >= 500 => self::MADRID_TO_BARCELONA,
            $km >= 250 => self::LENGTH_OF_JAMAICA,
            $km >= 100 => self::EDGE_OF_SPACE,
            default => null,
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::EDGE_OF_SPACE => $translator->trans("That's the distance to the edge of space, straight up", locale: $locale),
            self::LENGTH_OF_JAMAICA => $translator->trans("That's roughly the length of the island of Jamaica", locale: $locale),
            self::MADRID_TO_BARCELONA => $translator->trans("That's roughly the distance from Madrid to Barcelona", locale: $locale),
            self::FRANCE_LENGTH => $translator->trans("That's roughly the length of France", locale: $locale),
            self::DANUBE_RIVER => $translator->trans("That's roughly the length of the Danube river", locale: $locale),
            self::GREAT_WALL_OF_CHINA => $translator->trans("That's the length of the Great Wall of China", locale: $locale),
            self::QUARTER_AROUND_EARTH => $translator->trans("That's roughly a quarter of the way around the Earth", locale: $locale),
            self::LONDON_TO_PERTH => $translator->trans("That's roughly the distance from London to Perth", locale: $locale),
            self::HALFWAY_AROUND_EARTH => $translator->trans("That's more than halfway around the Earth", locale: $locale),
            self::CIRCUMFERENCE_OF_MARS => $translator->trans("That's more than the circumference of Mars", locale: $locale),
            self::THREE_QUARTERS_AROUND_EARTH => $translator->trans("That's three quarters of the way around the Earth", locale: $locale),
            self::EARTH_CIRCUMFERENCE => $translator->trans("That's the circumference of the Earth", locale: $locale),
            self::DIAMETER_OF_NEPTUNE => $translator->trans("That's roughly the diameter of Neptune", locale: $locale),
            self::NEARLY_TWICE_AROUND_EARTH => $translator->trans("That's nearly twice around the Earth", locale: $locale),
            self::TWO_AND_HALF_TIMES_AROUND_EARTH => $translator->trans("That's more than 2.5 times around the Earth", locale: $locale),
            self::DIAMETER_OF_JUPITER => $translator->trans("That's roughly the diameter of Jupiter", locale: $locale),
            self::FIVE_TIMES_AROUND_EARTH => $translator->trans("That's five times around the Earth", locale: $locale),
            self::SPEED_OF_LIGHT_PER_SECOND => $translator->trans("That's roughly the distance light travels in one second", locale: $locale),
            self::EARTH_TO_MOON => $translator->trans("That's the distance from Earth to the Moon", locale: $locale),
            self::MORE_THAN_EARTH_TO_MOON => $translator->trans("That's more than the distance from Earth to the Moon", locale: $locale),
        };
    }
}
