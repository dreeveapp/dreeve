<?php

namespace App\Tests\Controller\Admin\FileImport;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Domain\Import\FileImportStatus;
use App\Domain\Import\ImportMode;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Import\FileImportBuilder;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;

class ManageFileImportOverviewRequestHandlerTest extends AdminWebTestCase
{
    public function testRendersTheGatedPanelWhenNotInFileImportMode(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/file-imports');

        $this->assertResponseIsSuccessful();
        $gatedPanel = $crawler->filter('[role="alert"][type="gated-panel"]');
        $this->assertCount(1, $gatedPanel);
        $this->assertStringContainsString(
            'File imports are only available in file import mode',
            $gatedPanel->text()
        );
    }

    public function testRendersTheTableWithoutGatedPanelInFileImportMode(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/file-imports');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('[role="alert"][type="gated-panel"]'));
        $this->assertCount(1, $crawler->filter('table.data-table'));
    }

    public function testRendersTheEmptyStateWhenThereAreNoImports(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/file-imports');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No files imported yet.', $crawler->filter('body')->text());
        $this->assertCount(1, $crawler->filter('table.data-table tbody td[colspan="5"]'));
        $this->assertCount(0, $crawler->filter('[aria-label="Go to next page"]'));
        $this->assertCount(0, $crawler->filter('form[method="get"]'));
    }

    public function testRendersTheTableWithoutPaginationForASinglePage(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->seedFileImports(3);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/file-imports');

        $this->assertResponseIsSuccessful();
        $this->assertCount(3, $crawler->filter('table.data-table tbody tr'));
        $this->assertCount(3, $crawler->filter('table.data-table tbody a[href$="/delete"]'));
        $downloadLinks = $crawler->filter('table.data-table tbody a[href$="/download"]');
        $this->assertCount(2, $downloadLinks);
        $this->assertStringContainsString(
            '/admin/file-imports/'.FileImportId::fromUnprefixed('2').'/download',
            (string) $downloadLinks->first()->attr('href')
        );
        $this->assertSame('activity-2.fit', $downloadLinks->first()->attr('download'));
        $this->assertStringContainsString('activity-1.fit', $crawler->filter('table.data-table')->text());
        $this->assertStringNotContainsString('No files imported yet.', $crawler->filter('body')->text());
        $this->assertCount(0, $crawler->filter('[aria-label="Go to next page"]'));

        $this->assertCount(2, $crawler->filter('table.data-table [aria-label="Success"]'));
        $failed = $crawler->filter('table.data-table [aria-label="Failed"]');
        $this->assertCount(1, $failed);
        $this->assertSame('Could not parse activity-2.fit', $failed->attr('title'));

        $form = $crawler->filter('form[method="get"]');
        $this->assertCount(1, $form);
        $this->assertCount(1, $form->filter('select[name="filters[status]"]'));
        $this->assertCount(1, $form->filter('select[name="filters[source]"]'));
        $this->assertCount(1, $form->filter('input[name="filters[filename]"]'));
        $this->assertCount(1, $form->filter('input[name="filters[activity]"][data-autocomplete-url="/admin/activities/search"]'));
        $this->assertCount(1, $form->filter('button[type="submit"]'));
        $this->assertCount(0, $form->filter('select[name="filters[source]"] option[value="stravaApi"]'));
        $this->assertCount(0, $form->filter('a.btn--secondary'));
    }

    public function testRendersTheTableWithPaginationWhenResultsExceedASinglePage(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->seedFileImports(30);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/file-imports');

        $this->assertResponseIsSuccessful();
        $this->assertCount(25, $crawler->filter('table.data-table tbody tr'));
        $this->assertCount(1, $crawler->filter('[aria-label="Go to next page"]'));
        $this->assertStringContainsString('of 30', $crawler->filter('body')->text());
    }

    public function testRendersTheFilterFormWhenActiveFiltersMatchNothing(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->seedFileImports(3);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/file-imports?filters[source]=gpxFile');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('form[method="get"]'));
        $this->assertCount(0, $crawler->filter('table.data-table tbody tr td:not([colspan])'));

        $this->assertStringContainsString('No file imports match the current filters.', $crawler->filter('body')->text());
        $this->assertStringNotContainsString('No files imported yet.', $crawler->filter('body')->text());
        $this->assertStringContainsString('Clear filters', $crawler->filter('table.data-table tbody a')->text());
    }

    #[DataProvider('provideStatusFilterScenarios')]
    public function testRendersTheSubmittedStatusFilterBack(
        string $statusFilter,
        ?string $expectedSelectedOption,
    ): void {
        $this->withImportMode(ImportMode::FILES);
        $this->seedFileImports(3);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/file-imports?filters[status]='.$statusFilter);

        $this->assertResponseIsSuccessful();

        $selectedOption = $crawler->filter('select[name="filters[status]"] option[selected]');
        if (null === $expectedSelectedOption) {
            $this->assertCount(0, $selectedOption);
        } else {
            $this->assertSame($expectedSelectedOption, $selectedOption->attr('value'));
        }

        // The clear button only shows up when the active filters resolve to something usable.
        $this->assertCount(
            null === $expectedSelectedOption ? 0 : 1,
            $crawler->filter('form[method="get"] a.btn--secondary')
        );
    }

    public static function provideStatusFilterScenarios(): iterable
    {
        yield 'a valid status filter pre-selects the option' => [
            'failed', 'failed',
        ];

        yield 'an invalid status value is silently ignored' => [
            'bogus', null,
        ];
    }

    #[DataProvider('provideTextFilterScenarios')]
    public function testRendersTheSubmittedTextFiltersBack(
        string $query,
        string $expectedFilenameValue,
        string $expectedActivityValue,
    ): void {
        $this->withImportMode(ImportMode::FILES);
        $this->seedFileImports(3);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/file-imports?'.$query);

        $this->assertResponseIsSuccessful();
        $this->assertSame($expectedFilenameValue, $crawler->filter('input[name="filters[filename]"]')->attr('value'));
        $this->assertSame($expectedActivityValue, $crawler->filter('input[name="filters[activity]"]')->attr('value'));
    }

    public static function provideTextFilterScenarios(): iterable
    {
        yield 'a filename filter keeps its value' => [
            'filters[filename]=RIDE', 'RIDE', '',
        ];

        yield 'an activity filter renders the unprefixed id' => [
            'filters[activity]=activity-42', '', '42',
        ];

        yield 'a filename value is escaped when rendered back' => [
            'filters[filename]='.urlencode('"><script>x</script>'), '"><script>x</script>', '',
        ];
    }

    public function testFiltersArePreservedInPaginationLinks(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->seedFileImports(60);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/file-imports?filters[status]=failed');

        $this->assertResponseIsSuccessful();
        $this->assertCount(25, $crawler->filter('table.data-table tbody tr'));
        $this->assertStringContainsString('of 30', $crawler->filter('body')->text());
        $this->assertStringContainsString(
            'filters%5Bstatus%5D=failed',
            (string) $crawler->filter('[aria-label="Go to next page"]')->attr('href')
        );
    }

    public function testListsQueuedFilesFirstWithoutActionsOrStatusOption(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->seedFileImports(3);
        static::getContainer()->get(FilesystemOperator::class)->write('watch/queued.fit', 'raw fit bytes');
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/file-imports');

        $this->assertResponseIsSuccessful();
        $rows = $crawler->filter('table.data-table tbody tr');
        $this->assertCount(4, $rows);
        $this->assertCount(1, $rows->first()->filter('[aria-label="Queued"]'));
        $this->assertStringContainsString('queued.fit', $rows->first()->text());
        $this->assertCount(0, $rows->first()->filter('a'));
        $this->assertCount(3, $crawler->filter('table.data-table tbody a[href$="/delete"]'));
        $this->assertCount(0, $crawler->filter('select[name="filters[status]"] option[value="queued"]'));
    }

    public function testLinksEveryImportedActivityToItsDetailPage(): void
    {
        $this->withImportMode(ImportMode::FILES);

        $activityRepository = static::getContainer()->get(ActivityRepository::class);
        $fileImportRepository = static::getContainer()->get(FileImportRepository::class);

        foreach ([1, 2] as $i) {
            $activityId = ActivityId::fromUnprefixed((string) $i);
            $activityRepository->add(ActivityWithRawData::fromState(
                ActivityBuilder::fromDefaults()
                    ->withActivityId($activityId)
                    ->withName(sprintf('Activity %d', $i))
                    ->build(),
                [],
            ));
            $fileImportRepository->add(
                FileImportBuilder::fromDefaults()
                    ->withFileImportId(FileImportId::fromUnprefixed((string) $i))
                    ->withOriginalFilename(sprintf('activity-%d.fit', $i))
                    ->withActivityId($activityId)
                    ->build()
            );
        }

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/file-imports');

        $this->assertResponseIsSuccessful();

        $detailLinks = $crawler->filter('table.data-table tbody a[href^="/activities/"]');
        $this->assertCount(2, $detailLinks);
        $this->assertStringContainsString(
            '/activities/'.ActivityId::fromUnprefixed('1'),
            $detailLinks->first()->attr('href')
        );
        $this->assertSame('Activity 1', trim($detailLinks->first()->text()));
    }

    private function seedFileImports(int $count): void
    {
        $fileImportRepository = static::getContainer()->get(FileImportRepository::class);

        for ($i = 1; $i <= $count; ++$i) {
            $failed = 0 === $i % 2;
            $originalFileWasKept = 0 !== $i % 3;

            $fileImportRepository->add(
                FileImportBuilder::fromDefaults()
                    ->withFileImportId(FileImportId::fromUnprefixed((string) $i))
                    ->withOriginalFilename(sprintf('activity-%d.fit', $i))
                    ->withFileContents($originalFileWasKept ? 'raw fit bytes' : null)
                    ->withStatus($failed ? FileImportStatus::FAILED : FileImportStatus::SUCCESS)
                    ->withErrorMessage($failed ? sprintf('Could not parse activity-%d.fit', $i) : null)
                    ->withImportedOn(SerializableDateTime::fromString(sprintf('2026-06-01 %02d:%02d:00', 8 + intdiv($i, 60), $i % 60)))
                    ->build()
            );
        }
    }
}
