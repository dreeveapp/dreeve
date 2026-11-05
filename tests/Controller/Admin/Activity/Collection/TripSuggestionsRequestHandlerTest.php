<?php

namespace App\Tests\Controller\Admin\Activity\Collection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Route\RouteGeography;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Symfony\Component\DomCrawler\Crawler;

class TripSuggestionsRequestHandlerTest extends AdminWebTestCase
{
    public function testRendersTripSuggestions(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        for ($day = 1; $day <= 40; ++$day) {
            $activityRepository->add(ActivityWithRawData::fromState(ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('home-'.$day))
                ->withStartDateTime(SerializableDateTime::fromString('2024-01-01 10:00:00')->modify(sprintf('+%d days', $day - 1)))
                ->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('51.20'), Longitude::fromString('3.20')))
                ->build(), []));
        }
        foreach (['trip-1' => '2024-04-10', 'trip-2' => '2024-04-12'] as $activityId => $startDate) {
            $activityRepository->add(ActivityWithRawData::fromState(ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($activityId))
                ->withStartDateTime(SerializableDateTime::fromString($startDate.' 10:00:00'))
                ->withStartingCoordinate(Coordinate::createFromLatAndLng(Latitude::fromString('39.90'), Longitude::fromString('3.00')))
                ->withRouteGeography(RouteGeography::create(['country_code' => 'ES', 'city' => 'Pollença']))
                ->build(), []));
        }
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/collections/suggestions');

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            ['/admin/collections/suggestions'],
            $crawler->filter('nav.contextual-panel[aria-label="Activity collections"] a[aria-selected="true"]')->extract(['href']),
        );
        $this->assertSame('1', $crawler->filter('nav.contextual-panel[aria-label="Activity collections"] a[aria-selected="true"] span')->text());
        $suggestion = $crawler->filter('table.data-table tbody tr');
        $this->assertSame(
            [['10-04-2024 - 12-04-2024', 'Pollença', '2', 'Create collection']],
            $suggestion->each(
                static fn (Crawler $row): array => $row->filter('td')->each(static fn (Crawler $cell): string => preg_replace('/\s+/', ' ', trim($cell->text())) ?? ''),
            ),
        );
        $form = $suggestion->filter('form');
        $this->assertSame('add-activity-collection', $form->attr('data-dispatch-command'));
        $activityCollectionId = $form->filter('input[name="activityCollectionId"]')->attr('value');
        $this->assertSame('/admin/collections/'.$activityCollectionId.'/edit?redirectTo=%2Fadmin%2Fcollections%2Fsuggestions', $form->attr('data-redirect'));
        $this->assertSame('Spain, Apr 2024', $form->filter('input[name="name"]')->attr('value'));
        $this->assertSame('trip', $form->filter('input[name="type"]')->attr('value'));
        $this->assertSame(['activity-trip-1', 'activity-trip-2'], $form->filter('input[name="activityIds[]"]')->extract(['value']));
    }

    public function testRendersTheEmptyState(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/collections/suggestions');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No suggestions found.', $crawler->filter('table.data-table')->text());
        $this->assertCount(0, $crawler->filter('table.data-table form'));
        $this->assertCount(0, $crawler->filter('nav.contextual-panel a span'));
    }
}
