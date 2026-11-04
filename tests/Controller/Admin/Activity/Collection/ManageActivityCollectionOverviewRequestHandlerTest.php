<?php

namespace App\Tests\Controller\Admin\Activity\Collection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Domain\Import\ImportMode;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;
use Symfony\Component\DomCrawler\Crawler;

class ManageActivityCollectionOverviewRequestHandlerTest extends AdminWebTestCase
{
    public function testAsksToImportActivitiesFirstWhenThereAreNone(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/collections');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No activities imported yet.', $crawler->filter('body')->text());
        $this->assertStringNotContainsString('No collections added yet.', $crawler->filter('body')->text());
        $this->assertCount(1, $crawler->filter('table.data-table a.btn--primary[href$="/admin/upload"]'));
        $this->assertCount(0, $crawler->filter('a.btn--add'));
        $this->assertCount(1, $crawler->filter('#drawer-navigation a[href$="/admin/collections"][aria-selected="true"]'));
    }

    public function testRendersTheEmptyState(): void
    {
        $this->seedActivity();
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/collections');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No collections added yet.', $crawler->filter('body')->text());
        $this->assertStringNotContainsString('No activities imported yet.', $crawler->filter('body')->text());
        $this->assertSame('/admin/collections/add', $crawler->filter('a.btn--add')->attr('href'));
    }

    public function testRendersTheCollectionsNewestFirst(): void
    {
        $this->seedActivity();
        $activityCollectionRepository = $this->getContainer()->get(ActivityCollectionRepository::class);
        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withName('Mallorca')
            ->withType(ActivityCollectionType::TRIP)
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('1'), ActivityId::fromUnprefixed('2')]))
            ->withCreatedOn(SerializableDateTime::fromString('2025-04-20 10:00:00'))
            ->build());
        $activityCollectionRepository->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('2'))
            ->withName('Transalp 2025')
            ->withType(ActivityCollectionType::EVENT)
            ->withCreatedOn(SerializableDateTime::fromString('2025-06-26 10:00:00'))
            ->build());
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/collections');

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            [['Transalp 2025', 'Event', '0', 'Edit'], ['Mallorca', 'Trip', '2', 'Edit']],
            $crawler->filter('table.data-table tbody tr')->each(
                static fn (Crawler $row): array => $row->filter('td')->each(static fn (Crawler $cell): string => trim($cell->text())),
            ),
        );
        $this->assertSame(
            ['/admin/collections/activityCollection-2/edit', '/admin/collections/activityCollection-1/edit'],
            $crawler->filter('table.data-table tbody a[title="Edit"]')->extract(['href']),
        );
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
