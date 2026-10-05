<?php

namespace App\Tests\Controller\Page\Gear;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\ProvideTestData;
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
            [['Retro Race Bike', '1', '0kcal'], ['Zwift hub', '1', '0kcal']],
            $crawler->filter('#table-gear tbody tr')->each(
                fn (Crawler $row): array => [$row->filter('th')->text(), $row->filter('td')->eq(0)->text(), $row->filter('td')->eq(5)->text()],
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
            [['Unspecified', '1', '0kcal']],
            $crawler->filter('#table-gear tbody tr')->each(
                fn (Crawler $row): array => [$row->filter('th')->text(), $row->filter('td')->eq(0)->text(), $row->filter('td')->eq(5)->text()],
            ),
        );
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
