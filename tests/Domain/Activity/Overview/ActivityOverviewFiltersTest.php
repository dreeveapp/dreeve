<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Overview;

use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Overview\ActivityOverviewFilters;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Gear\GearId;
use App\Domain\Settings\ActivityExclusion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ActivityOverviewFiltersTest extends TestCase
{
    public function testItReturnsNullWhenNoFiltersAreGiven(): void
    {
        $filters = ActivityOverviewFilters::fromRequest(new Request());

        $this->assertNull($filters->getSportType());
        $this->assertNull($filters->getGearId());
        $this->assertNull($filters->getDevice());
        $this->assertNull($filters->getImportSource());
        $this->assertNull($filters->getExcludedFrom());
        $this->assertTrue($filters->isEmpty());
    }

    public function testItReadsAllFiltersFromTheNestedQueryParam(): void
    {
        $filters = ActivityOverviewFilters::fromRequest(new Request(query: [
            'filters' => [
                'sportType' => 'Run',
                'gear' => (string) GearId::fromUnprefixed('99'),
                'device' => 'Garmin Forerunner',
                'importSource' => 'fitFile',
                'excludedFrom' => 'bestEfforts',
            ],
        ]));

        $this->assertEquals(SportType::RUN, $filters->getSportType());
        $this->assertEquals(GearId::fromUnprefixed('99'), $filters->getGearId());
        $this->assertSame('Garmin Forerunner', $filters->getDevice());
        $this->assertEquals(ImportSource::FIT_FILE, $filters->getImportSource());
        $this->assertSame(ActivityExclusion::BEST_EFFORTS, $filters->getExcludedFrom());
        $this->assertFalse($filters->isEmpty());
    }

    public function testItIsNotEmptyWhenOnlyOneFilterIsSet(): void
    {
        $this->assertFalse(ActivityOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['sportType' => 'Run'],
        ]))->isEmpty());
        $this->assertFalse(ActivityOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['gear' => (string) GearId::fromUnprefixed('99')],
        ]))->isEmpty());
        $this->assertFalse(ActivityOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['device' => 'Garmin Forerunner'],
        ]))->isEmpty());
        $this->assertFalse(ActivityOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['importSource' => 'gpxFile'],
        ]))->isEmpty());
        $this->assertFalse(ActivityOverviewFilters::fromRequest(new Request(query: [
            'filters' => ['excludedFrom' => 'peakPowerOutputs'],
        ]))->isEmpty());
    }

    #[DataProvider('provideIgnoredFilterValues')]
    public function testItSilentlyIgnoresUnusableValues(array $query): void
    {
        $filters = ActivityOverviewFilters::fromRequest(new Request(query: $query));

        $this->assertNull($filters->getSportType());
        $this->assertNull($filters->getGearId());
        $this->assertNull($filters->getDevice());
        $this->assertNull($filters->getImportSource());
        $this->assertNull($filters->getExcludedFrom());
        $this->assertTrue($filters->isEmpty());
    }

    public static function provideIgnoredFilterValues(): iterable
    {
        yield 'values that are not backed enum values or valid identifiers' => [
            ['filters' => ['sportType' => 'bogus', 'gear' => 'not-a-gear-id', 'importSource' => 'bogus', 'excludedFrom' => 'bogus']],
        ];

        yield 'empty strings, as submitted by the "All" options' => [
            ['filters' => ['sportType' => '', 'gear' => '', 'device' => '', 'importSource' => '', 'excludedFrom' => '']],
        ];

        yield 'nested arrays instead of scalar values' => [
            ['filters' => ['sportType' => ['Run'], 'gear' => ['gear-99'], 'device' => ['x'], 'importSource' => ['fitFile'], 'excludedFrom' => ['bestEfforts']]],
        ];

        yield 'unrelated filter names' => [
            ['filters' => ['foo' => 'bar']],
        ];
    }
}
