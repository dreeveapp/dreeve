<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser\Fit;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Import\FileParser\Fit\FitSportType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FitSportTypeTest extends TestCase
{
    #[DataProvider('provideSportMappings')]
    public function testResolve(?int $sport, ?int $subSport, ?SportType $expectedSportType): void
    {
        $this->assertSame($expectedSportType, FitSportType::resolve($sport, $subSport));
    }

    public static function provideSportMappings(): array
    {
        return [
            'running' => [1, null, SportType::RUN],
            'running / street' => [1, 2, SportType::RUN],
            'running / trail' => [1, 3, SportType::TRAIL_RUN],
            'running / treadmill' => [1, 1, SportType::VIRTUAL_RUN],
            'running / indoor' => [1, 45, SportType::VIRTUAL_RUN],
            'running / virtual' => [1, 58, SportType::VIRTUAL_RUN],
            'cycling' => [2, null, SportType::RIDE],
            'cycling / road' => [2, 7, SportType::RIDE],
            'cycling / mountain' => [2, 8, SportType::MOUNTAIN_BIKE_RIDE],
            'cycling / downhill' => [2, 9, SportType::MOUNTAIN_BIKE_RIDE],
            'cycling / cyclocross' => [2, 11, SportType::MOUNTAIN_BIKE_RIDE],
            'cycling / enduro' => [2, 123, SportType::MOUNTAIN_BIKE_RIDE],
            'cycling / gravel' => [2, 46, SportType::GRAVEL_RIDE],
            'cycling / indoor' => [2, 6, SportType::VIRTUAL_RIDE],
            'cycling / spin' => [2, 5, SportType::VIRTUAL_RIDE],
            'cycling / virtual' => [2, 58, SportType::VIRTUAL_RIDE],
            'cycling / e-bike fitness' => [2, 28, SportType::E_BIKE_RIDE],
            'cycling / e-bike mountain' => [2, 47, SportType::E_BIKE_RIDE],
            'cycling / e-bike enduro' => [2, 127, SportType::E_BIKE_RIDE],
            'cycling / hand cycling' => [2, 12, SportType::HAND_CYCLE],
            'cycling / indoor hand cycling' => [2, 88, SportType::HAND_CYCLE],
            'fitness equipment' => [4, null, SportType::WORKOUT],
            'fitness equipment / generic' => [4, 0, SportType::WORKOUT],
            'fitness equipment / indoor rowing' => [4, 14, SportType::VIRTUAL_ROW],
            'fitness equipment / elliptical' => [4, 15, SportType::ELLIPTICAL],
            'fitness equipment / stair climbing' => [4, 16, SportType::STAIR_STEPPER],
            'fitness equipment / indoor cycling' => [4, 6, SportType::VIRTUAL_RIDE],
            'fitness equipment / spin' => [4, 5, SportType::VIRTUAL_RIDE],
            'fitness equipment / treadmill' => [4, 1, SportType::VIRTUAL_RUN],
            'fitness equipment / indoor walking' => [4, 27, SportType::WALK],
            'fitness equipment / pilates' => [4, 44, SportType::PILATES],
            'swimming' => [5, null, SportType::SWIM],
            'swimming / lap swimming' => [5, 17, SportType::SWIM],
            'basketball' => [6, null, SportType::BASKETBALL],
            'soccer' => [7, null, SportType::SOCCER],
            'tennis' => [8, null, SportType::TENNIS],
            'training' => [10, null, SportType::WORKOUT],
            'training / strength' => [10, 20, SportType::WEIGHT_TRAINING],
            'training / yoga' => [10, 43, SportType::YOGA],
            'training / pilates' => [10, 44, SportType::PILATES],
            'training / hiit' => [10, 70, SportType::HIIT],
            'walking' => [11, null, SportType::WALK],
            'cross country skiing' => [12, null, SportType::NORDIC_SKI],
            'alpine skiing' => [13, null, SportType::ALPINE_SKI],
            'alpine skiing / resort' => [13, 38, SportType::ALPINE_SKI],
            'alpine skiing / backcountry' => [13, 37, SportType::BACK_COUNTRY_SKI],
            'snowboarding' => [14, null, SportType::SNOWBOARD],
            'rowing' => [15, null, SportType::ROWING],
            'rowing / indoor rowing' => [15, 14, SportType::VIRTUAL_ROW],
            'hiking' => [17, null, SportType::HIKE],
            'paddling' => [19, null, SportType::CANOEING],
            'e-biking' => [21, null, SportType::E_BIKE_RIDE],
            'golf' => [25, null, SportType::GOLF],
            'inline skating' => [30, null, SportType::INLINE_SKATE],
            'rock climbing' => [31, null, SportType::ROCK_CLIMBING],
            'rock climbing / bouldering' => [31, 69, SportType::ROCK_CLIMBING],
            'sailing' => [32, null, SportType::SAIL],
            'ice skating' => [33, null, SportType::ICE_SKATE],
            'snowshoeing' => [35, null, SportType::SNOWSHOE],
            'stand up paddleboarding' => [37, null, SportType::STAND_UP_PADDLING],
            'surfing' => [38, null, SportType::SURFING],
            'kayaking' => [41, 0, SportType::KAYAKING],
            'kayaking / whitewater' => [41, 41, SportType::KAYAKING],
            'windsurfing' => [43, null, SportType::WIND_SURF],
            'kitesurfing' => [44, null, SportType::KITE_SURF],
            'floor climbing' => [48, null, SportType::STAIR_STEPPER],
            'hiit' => [62, null, SportType::HIIT],
            'racket' => [64, null, SportType::WORKOUT],
            'racket / pickleball' => [64, 84, SportType::PICKLE_BALL],
            'racket / padel' => [64, 85, SportType::PADEL],
            'racket / squash' => [64, 94, SportType::SQUASH],
            'racket / badminton' => [64, 95, SportType::BADMINTON],
            'racket / racquetball' => [64, 96, SportType::RACQUET_BALL],
            'racket / table tennis' => [64, 97, SportType::TABLE_TENNIS],
            'wheelchair push walk' => [65, null, SportType::WHEELCHAIR],
            'wheelchair push run' => [66, null, SportType::WHEELCHAIR],
            'cricket' => [71, null, SportType::CRICKET],
            'volleyball' => [75, null, SportType::VOLLEYBALL],
            'dance' => [83, null, SportType::DANCE],
            'canoeing' => [88, null, SportType::CANOEING],
            'generic' => [0, null, null],
            'transition' => [3, null, null],
            'flying' => [20, null, null],
            'driving' => [24, null, null],
            'null sport' => [null, null, null],
        ];
    }
}
