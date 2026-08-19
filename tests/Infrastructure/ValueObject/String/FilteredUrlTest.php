<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ValueObject\String;

use App\Application\AppUrl;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Infrastructure\ValueObject\String\FilteredUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FilteredUrlTest extends TestCase
{
    #[DataProvider(methodName: 'provideFilters')]
    public function testToRelativeUrl(string $expected, array $filters): void
    {
        $this->assertEquals(
            $expected,
            FilteredUrl::from('activities', $filters, AppUrl::fromString('http://localhost:8081/base/'))->toRelativeUrl()
        );
    }

    public static function provideFilters(): array
    {
        return [
            'no filters' => ['/base/activities', []],
            'a stringable identifier' => ['/base/activities?filters[gear]=gear-99', ['gear' => GearId::fromUnprefixed('99')]],
            'a list of backed enums' => ['/base/activities?filters[sportType][]=Run&filters[sportType][]=Ride', ['sportType' => [SportType::RUN, SportType::RIDE]]],
            'a collection of backed enums' => ['/base/activities?filters[sportType][]=Run&filters[sportType][]=Ride', ['sportType' => SportTypes::fromArray([SportType::RUN, SportType::RIDE])]],
            'a range' => ['/base/activities?filters[distance][from]=10&filters[distance][to]=20', ['distance' => ['from' => 10, 'to' => 20]]],
            'a half open range' => ['/base/activities?filters[distance][to]=20', ['distance' => ['from' => null, 'to' => 20]]],
            'a value needing encoding' => ['/base/activities?filters[device]=Garmin%20Forerunner%20255', ['device' => 'Garmin Forerunner 255']],
            'several filters' => ['/base/activities?filters[sportType][]=Run&filters[distance][from]=10', ['sportType' => [SportType::RUN], 'distance' => ['from' => 10, 'to' => null]]],
        ];
    }

    public function testToRelativeUrlWithoutBasePath(): void
    {
        $this->assertEquals(
            '/activities?filters[gear]=gear-99',
            FilteredUrl::from('/activities', ['gear' => GearId::fromUnprefixed('99')], AppUrl::fromString('http://localhost:8081'))->toRelativeUrl()
        );
    }
}
