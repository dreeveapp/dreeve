<?php

namespace App\Tests\Application;

use App\Application\Countries;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\RouteGeography;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class CountriesTest extends ContainerTestCase
{
    private Countries $countries;

    public function testGetUsedInActivities(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withRouteGeography(RouteGeography::create([
                    'country_code' => 'pl',
                    RouteGeography::PASSED_TROUGH_COUNTRIES => ['PL', 'CZ'],
                ]))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withRouteGeography(RouteGeography::create([
                    'country_code' => 'be',
                    RouteGeography::PASSED_TROUGH_COUNTRIES => ['BE'],
                ]))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->build(),
            []
        ));

        $this->assertEqualsCanonicalizing(
            ['pl', 'cz', 'be'],
            array_keys($this->countries->getUsedInActivities())
        );
    }

    public function testGetUsedInPhotos(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withTotalImageCount(3)
                ->withRouteGeography(RouteGeography::create([
                    'country_code' => 'pl',
                    RouteGeography::PASSED_TROUGH_COUNTRIES => ['PL', 'CZ'],
                ]))
                ->build(),
            []
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withTotalImageCount(0)
                ->withRouteGeography(RouteGeography::create([
                    'country_code' => 'be',
                    RouteGeography::PASSED_TROUGH_COUNTRIES => ['BE', 'NL'],
                ]))
                ->build(),
            []
        ));

        $this->assertEqualsCanonicalizing(
            ['pl', 'cz'],
            array_keys($this->countries->getUsedInPhotos())
        );
    }

    public function testFindCountryCodeByCountryName(): void
    {
        $this->assertEquals(
            'BE',
            $this->countries->findCountryCodeByCountryName('Belgium')
        );
        $this->assertNull($this->countries->findCountryCodeByCountryName('Robinstan'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->countries = $this->getContainer()->get(Countries::class);
    }
}
