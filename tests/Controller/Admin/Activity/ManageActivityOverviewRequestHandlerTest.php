<?php

namespace App\Tests\Controller\Admin\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Import\ImportMode;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class ManageActivityOverviewRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->seedActivity();

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
        $this->assertCount(1, $crawler->filter('table.data-table tbody td[colspan="9"]'));
        $this->assertCount(0, $crawler->filter('table.data-table tbody a[title="Edit"]'));
        $this->assertCount(0, $crawler->filter('[aria-label="Go to next page"]'));
    }

    public function testRendersTheTableWithoutPaginationForASinglePage(): void
    {
        $this->seedActivities(3);
        $this->seedActivity();
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

        $form = $crawler->filter('form[method="get"]');
        $this->assertCount(1, $form);
        $this->assertCount(1, $form->filter('select[name="filters[sportType]"]'));
        $this->assertCount(1, $form->filter('select[name="filters[gear]"]'));
        $this->assertCount(1, $form->filter('select[name="filters[device]"]'));
        $this->assertCount(1, $form->filter('select[name="filters[importSource]"]'));
        $this->assertCount(1, $form->filter('button[type="submit"]'));
        $this->assertCount(0, $form->filter('a.btn--secondary'));

        $gearOption = $form->filter('select[name="filters[gear]"] option:not([value=""])');
        $this->assertCount(1, $gearOption);
        $this->assertSame((string) GearId::fromUnprefixed('99'), $gearOption->attr('value'));
        $this->assertSame('Trail Shoes', $gearOption->text());
    }

    public function testLinksEveryActivityToItsDetailPage(): void
    {
        $this->seedActivities(2);
        $this->seedActivity();

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/activities');

        $this->assertResponseIsSuccessful();

        $detailLinks = $crawler->filter('table.data-table tbody a[href^="/activities/"]');
        $this->assertCount(2, $detailLinks);
        $this->assertStringContainsString(
            '/activities/'.ActivityId::fromUnprefixed('1'),
            $detailLinks->first()->attr('href')
        );
        $this->assertSame('Activity 1', trim($detailLinks->first()->text()));
    }

    public function testWarnsAboutActivitiesThatCanBeEnriched(): void
    {
        $activityRepository = static::getContainer()->get(ActivityRepository::class);
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withSportType(SportType::WALK)
                ->withStartDateTime(SerializableDateTime::fromString('2026-06-02 08:00:00'))
                ->withStartingCoordinate(Coordinate::createFromLatAndLng(
                    Latitude::fromString('50.80'),
                    Longitude::fromString('4.94'),
                ))
                ->build(),
            [],
        ));
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withSportType(SportType::VIRTUAL_RIDE)
                ->withStartDateTime(SerializableDateTime::fromString('2026-06-01 08:00:00'))
                ->build(),
            [],
        ));

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/activities');

        $this->assertResponseIsSuccessful();

        $rows = $crawler->filter('table.data-table tbody tr');
        $this->assertCount(1, $rows->eq(0)->filter('span.text-amber-500 svg'));
        $this->assertCount(1, $rows->eq(0)->filter('form[data-dispatch-command="enrich-activity"]'));
        $this->assertCount(1, $rows->eq(0)->filter('form[data-dispatch-command="enrich-activity"] button[data-has-loading-state]'));
        $this->assertStringContainsString(
            'group-[.is-loading]:animate-spin',
            $rows->eq(0)->filter('form[data-dispatch-command="enrich-activity"] svg')->attr('class')
        );
        $this->assertSame(
            (string) ActivityId::fromUnprefixed('1'),
            $rows->eq(0)->filter('form[data-dispatch-command="enrich-activity"] input[name="activityId"]')->attr('value')
        );

        $this->assertCount(0, $rows->eq(1)->filter('span.text-amber-500 svg'));
        $this->assertCount(0, $rows->eq(1)->filter('form[data-dispatch-command="enrich-activity"]'));
    }

    #[DataProvider('provideSportTypeFilterScenarios')]
    public function testFiltersTheTableOnSportType(
        string $sportTypeFilter,
        int $expectedRowCount,
        ?string $expectedSelectedOption,
    ): void {
        $this->seedActivities(3);
        $this->seedActivity();
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/activities?filters[sportType]='.$sportTypeFilter);

        $this->assertResponseIsSuccessful();
        $this->assertCount($expectedRowCount, $crawler->filter('table.data-table tbody tr'));

        $selectedOption = $crawler->filter('select[name="filters[sportType]"] option[selected]');
        if (null === $expectedSelectedOption) {
            $this->assertCount(0, $selectedOption);
        } else {
            $this->assertSame($expectedSelectedOption, $selectedOption->attr('value'));
        }

        $this->assertCount(
            null === $expectedSelectedOption ? 0 : 1,
            $crawler->filter('form[method="get"] a.btn--secondary')
        );
    }

    public static function provideSportTypeFilterScenarios(): iterable
    {
        yield 'a valid sport type filter narrows the rows and pre-selects the option' => [
            'Run', 1, 'Run',
        ];

        yield 'an invalid sport type value is silently ignored' => [
            'bogus', 3, null,
        ];
    }

    public function testRendersTheFilterFormWhenActiveFiltersMatchNothing(): void
    {
        $this->seedActivities(3);
        $this->seedActivity();
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/activities?filters[device]=Nonexistent');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('form[method="get"]'));
        $this->assertCount(0, $crawler->filter('table.data-table tbody tr td:not([colspan])'));

        // The empty state explains that the filters caused it, instead of suggesting nothing was imported yet.
        $this->assertStringContainsString('No activities match the current filters.', $crawler->filter('body')->text());
        $this->assertStringNotContainsString('No activities imported yet.', $crawler->filter('body')->text());
        $this->assertStringContainsString('Clear filters', $crawler->filter('table.data-table tbody a')->text());
    }

    public function testRendersTheTableWithPaginationWhenResultsExceedASinglePage(): void
    {
        $this->seedActivities(30);
        $this->seedActivity();
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/activities');

        $this->assertResponseIsSuccessful();
        $this->assertCount(25, $crawler->filter('table.data-table tbody tr'));
        $this->assertCount(1, $crawler->filter('[aria-label="Go to next page"]'));
        $this->assertStringContainsString('of 30', $crawler->filter('body')->text());

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

    public function testShowsTheImportSourceOfEveryActivity(): void
    {
        $this->withImportMode(ImportMode::FILES);

        $this->seedActivities(1);
        static::getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('99'))
                ->withImportSource(ImportSource::MANUAL)
                ->withStartDateTime(SerializableDateTime::fromString('2026-07-01 08:00:00'))
                ->build(),
            [],
        ));

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/activities');

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            ['Manual', 'Strava API'],
            $crawler->filter('table.data-table tbody tr td:nth-child(4)')->each(fn ($cell) => trim($cell->text())),
        );
    }

    public function testShowsImportAndAddButtonsInFilesMode(): void
    {
        $this->withImportMode(ImportMode::FILES);

        $this->seedActivities(1);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/activities');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('/admin/upload', (string) $crawler->filter('a:contains("Import activities")')->attr('href'));
        $this->assertStringContainsString('/admin/activities/add', (string) $crawler->filter('a:contains("Add activity")')->attr('href'));
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }

    private function seedActivities(int $count): void
    {
        $activityRepository = static::getContainer()->get(ActivityRepository::class);
        $gearRepository = static::getContainer()->get(GearRepository::class);

        $gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('99'))
                ->withName('Trail Shoes')
                ->build()
        );

        for ($i = 1; $i <= $count; ++$i) {
            $activityRepository->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed((string) $i))
                    ->withName(sprintf('Activity %d', $i))
                    ->withSportType(0 === $i % 2 ? SportType::RUN : SportType::RIDE)
                    ->withGearId(GearId::fromUnprefixed('99'))
                    ->withStartDateTime(SerializableDateTime::fromString(sprintf('2026-06-%02d 08:00:00', $count - $i + 1)))
                    ->build(),
                [],
            ));
        }
    }
}
