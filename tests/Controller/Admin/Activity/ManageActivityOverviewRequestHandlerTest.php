<?php

namespace App\Tests\Controller\Admin\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Import\ImportMode;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;

class ManageActivityOverviewRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->markAppAsBuilt();

        $this->client->request('GET', '/admin/activities');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testRendersTheEmptyStateWhenThereAreNoActivities(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/activities');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('table.data-table'));
        $this->assertStringContainsString('No activities imported yet.', $crawler->filter('body')->text());
        $this->assertCount(1, $crawler->filter('table.data-table tbody td[colspan="8"]'));
        $this->assertCount(0, $crawler->filter('table.data-table tbody a[title="Edit"]'));
        $this->assertCount(0, $crawler->filter('[aria-label="Go to next page"]'));
    }

    public function testRendersTheTableWithoutPaginationForASinglePage(): void
    {
        $this->seedActivities(3);
        $this->markAppAsBuilt();
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/activities');

        $this->assertResponseIsSuccessful();
        $this->assertCount(3, $crawler->filter('table.data-table tbody tr'));
        $this->assertStringContainsString('Activity 1', $crawler->filter('table.data-table')->text());
        $this->assertStringNotContainsString('No activities imported yet.', $crawler->filter('body')->text());
        $this->assertCount(0, $crawler->filter('[aria-label="Go to next page"]'));

        $editLinks = $crawler->filter('table.data-table tbody a[title="Edit"]');
        $this->assertCount(3, $editLinks);
        $this->assertStringContainsString(
            '/admin/activities/'.ActivityId::fromUnprefixed('1').'/edit',
            $editLinks->first()->attr('href')
        );
    }

    public function testRendersTheTableWithPaginationWhenResultsExceedASinglePage(): void
    {
        $this->seedActivities(30);
        $this->markAppAsBuilt();
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/activities');

        $this->assertResponseIsSuccessful();
        $this->assertCount(25, $crawler->filter('table.data-table tbody tr'));
        $this->assertCount(1, $crawler->filter('[aria-label="Go to next page"]'));
        $this->assertStringContainsString('of 30', $crawler->filter('body')->text());

        // The default import mode in tests is the Strava API, where activities can't be deleted.
        $this->assertCount(0, $crawler->filter('table.data-table tbody a[title="Delete"]'));
    }

    public function testShowsDeleteLinksInFilesMode(): void
    {
        $this->withImportMode(ImportMode::FILES);

        $this->seedActivities(3);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/activities');

        $this->assertResponseIsSuccessful();
        $deleteLinks = $crawler->filter('table.data-table tbody a[title="Delete"]');
        $this->assertCount(3, $deleteLinks);
        $this->assertStringContainsString(
            '/admin/activities/'.ActivityId::fromUnprefixed('1').'/delete',
            $deleteLinks->first()->attr('href')
        );
    }

    #[\Override]
    protected function shouldMarkAppAsBuilt(): bool
    {
        return false;
    }

    private function seedActivities(int $count): void
    {
        $activityRepository = static::getContainer()->get(ActivityRepository::class);

        for ($i = 1; $i <= $count; ++$i) {
            $activityRepository->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed((string) $i))
                    ->withName(sprintf('Activity %d', $i))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2026-06-%02d 08:00:00', $count - $i + 1)))
                    ->build(),
                [],
            ));
        }
    }
}
