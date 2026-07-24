<?php

declare(strict_types=1);

namespace App\Domain\Milestone\FunComparison;

use App\Infrastructure\Measurement\Length\Meter;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ElevationFunComparison: string implements FunComparison
{
    case EIFFEL_TOWER = 'elevationEiffelTower';
    case EMPIRE_STATE_BUILDING = 'elevationEmpireStateBuilding';
    case TWO_EIFFEL_TOWERS = 'elevationTwoEiffelTowers';
    case DEEPEST_CAVE = 'elevationDeepestCave';
    case HIGHER_THAN_MONT_BLANC = 'elevationHigherThanMontBlanc';
    case MOUNT_EVEREST = 'elevationMountEverest';
    case CRUISING_ALTITUDE = 'elevationCruisingAltitude';
    case EVEREST_TWICE = 'elevationEverestTwice';
    case NEARLY_EVEREST_THREE_TIMES = 'elevationNearlyEverestThreeTimes';
    case BAUMGARTNER_SPACE_JUMP = 'elevationBaumgartnerSpaceJump';
    case EVEREST_EIGHT_TIMES = 'elevationEverestEightTimes';
    case KARMAN_LINE = 'elevationKarmanLine';
    case ABOVE_KARMAN_LINE = 'elevationAboveKarmanLine';
    case HALFWAY_TO_ISS = 'elevationHalfwayToIss';
    case LOW_EARTH_ORBIT = 'elevationLowEarthOrbit';
    case ISS_ALTITUDE = 'elevationIssAltitude';
    case HIGHER_THAN_ISS = 'elevationHigherThanIss';
    case TWICE_ISS_ALTITUDE = 'elevationTwiceIssAltitude';
    case EDGE_OF_SPACE_FIVE_TIMES = 'elevationEdgeOfSpaceFiveTimes';

    public static function resolve(Meter $elevation): ?self
    {
        $m = $elevation->toFloat();

        return match (true) {
            $m >= 1_000_000 => self::EDGE_OF_SPACE_FIVE_TIMES,
            $m >= 750_000 => self::TWICE_ISS_ALTITUDE,
            $m >= 500_000 => self::HIGHER_THAN_ISS,
            $m >= 400_000 => self::ISS_ALTITUDE,
            $m >= 300_000 => self::LOW_EARTH_ORBIT,
            $m >= 200_000 => self::HALFWAY_TO_ISS,
            $m >= 150_000 => self::ABOVE_KARMAN_LINE,
            $m >= 100_000 => self::KARMAN_LINE,
            $m >= 75_000 => self::EVEREST_EIGHT_TIMES,
            $m >= 50_000 => self::BAUMGARTNER_SPACE_JUMP,
            $m >= 25_000 => self::NEARLY_EVEREST_THREE_TIMES,
            $m >= 17_772 => self::EVEREST_TWICE,
            $m >= 10_000 => self::CRUISING_ALTITUDE,
            $m >= 8_849 => self::MOUNT_EVEREST,
            $m >= 5_000 => self::HIGHER_THAN_MONT_BLANC,
            $m >= 2_500 => self::DEEPEST_CAVE,
            $m >= 1_000 => self::TWO_EIFFEL_TOWERS,
            $m >= 500 => self::EMPIRE_STATE_BUILDING,
            $m >= 300 => self::EIFFEL_TOWER,
            default => null,
        };
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::EIFFEL_TOWER => $translator->trans("That's the height of the Eiffel Tower", locale: $locale),
            self::EMPIRE_STATE_BUILDING => $translator->trans("That's taller than the Empire State Building", locale: $locale),
            self::TWO_EIFFEL_TOWERS => $translator->trans("That's more than 2 Eiffel Towers stacked up", locale: $locale),
            self::DEEPEST_CAVE => $translator->trans("That's deeper than the deepest known cave on Earth", locale: $locale),
            self::HIGHER_THAN_MONT_BLANC => $translator->trans("That's higher than the summit of Mont Blanc", locale: $locale),
            self::MOUNT_EVEREST => $translator->trans("That's the height of Mount Everest", locale: $locale),
            self::CRUISING_ALTITUDE => $translator->trans("That's cruising altitude of a commercial airplane", locale: $locale),
            self::EVEREST_TWICE => $translator->trans("That's climbing Mount Everest twice", locale: $locale),
            self::NEARLY_EVEREST_THREE_TIMES => $translator->trans("That's nearly climbing Mount Everest 3 times", locale: $locale),
            self::BAUMGARTNER_SPACE_JUMP => $translator->trans("That's higher than Felix Baumgartner's space jump", locale: $locale),
            self::EVEREST_EIGHT_TIMES => $translator->trans("That's more than 8 times the height of Mount Everest", locale: $locale),
            self::KARMAN_LINE => $translator->trans("That's the edge of space, the Kármán line", locale: $locale),
            self::ABOVE_KARMAN_LINE => $translator->trans("That's 1.5 times the distance to the edge of space", locale: $locale),
            self::HALFWAY_TO_ISS => $translator->trans("That's more than halfway to the ISS", locale: $locale),
            self::LOW_EARTH_ORBIT => $translator->trans("That's the altitude of a satellite in low Earth orbit", locale: $locale),
            self::ISS_ALTITUDE => $translator->trans("That's nearly the altitude of the International Space Station", locale: $locale),
            self::HIGHER_THAN_ISS => $translator->trans("That's higher than the International Space Station's orbit", locale: $locale),
            self::TWICE_ISS_ALTITUDE => $translator->trans("That's nearly twice the altitude of the International Space Station", locale: $locale),
            self::EDGE_OF_SPACE_FIVE_TIMES => $translator->trans("That's climbing to the edge of space and back 5 times", locale: $locale),
        };
    }
}
