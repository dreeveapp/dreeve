<?php

namespace App\Tests\Controller\Page\Gear;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\ProvideTestData;
use Money\Money;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\DomCrawler\Crawler;

class GearStatsRequestHandlerTest extends AdminWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('testy'))
                ->withGearId(GearId::fromUnprefixed('testy'))
                ->build(),
            rawData: []
        ));

        $this->client->request('GET', '/gear');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringEndsWith(
            'gear.auth=anon',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, gear, activities',
        );
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWithoutUnspecifiedGear(): void
    {
        $this->addGeneralFixtures();
        $this->addGearFixtures();

        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withGearId(GearId::fromUnprefixed('b12659861'))
                ->build(),
            rawData: []
        ));
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId(GearId::fromUnprefixed('b12659862'))
                ->build(),
            rawData: []
        ));

        $this->seedActivity();

        $crawler = $this->client->request('GET', '/gear');

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            [['Retro Race Bike', 'Rides · 1 workouts', '0kcal'], ['Zwift hub', 'Rides · 1 workouts', '0kcal']],
            $crawler->filter('details.group\\/row')->each(
                fn (Crawler $row): array => [
                    $row->filter('summary .font-medium')->text(),
                    $row->filter('summary .text-xs')->first()->text(),
                    $row->filter('.grid-cols-3 .font-bold')->last()->text(),
                ],
            ),
        );
    }

    public function testRenderWithAnUnspecifiedGearWithoutCalories(): void
    {
        $this->addGeneralFixtures();
        $this->addGearFixtures();

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withoutGearId()
                ->withCalories(null)
                ->build(),
            rawData: []
        ));

        $this->seedActivity();

        $crawler = $this->client->request('GET', '/gear');

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            [['Unspecified', 'Rides · 1 workouts', '0kcal']],
            $crawler->filter('details.group\\/row')->each(
                fn (Crawler $row): array => [
                    $row->filter('summary .font-medium')->text(),
                    $row->filter('summary .text-xs')->first()->text(),
                    $row->filter('.grid-cols-3 .font-bold')->last()->text(),
                ],
            ),
        );
    }

    public function testRenderWithAPurchasePrice(): void
    {
        $this->addGeneralFixtures();

        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withPurchasePrice(Money::EUR(100000))
                ->build()
        );
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withDistance(Kilometer::from(100))
                ->withMovingTimeInSeconds(7200)
                ->build(),
            rawData: []
        ));

        $crawler = $this->client->request('GET', '/gear');

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            ['€1,000.00', '€10.00', '€500.00', '€1,000.00'],
            $crawler->filter('details.group\\/row .grid-cols-2 .font-bold')->each(fn (Crawler $cost): string => $cost->text()),
        );
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
