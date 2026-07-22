<?php

namespace App\Tests\Controller\Admin\File;

use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Domain\Import\FileImportStatus;
use App\Domain\Import\ImportMode;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Import\FileImportBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class ManageFileImportOverviewRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/file-imports');

        $this->assertResponseRedirects('/admin/login');
    }

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
    public function testFiltersTheTableOnStatus(
        string $statusFilter,
        int $expectedRowCount,
        int $expectedFailedCount,
        int $expectedSuccessCount,
        ?string $expectedSelectedOption,
    ): void {
        $this->withImportMode(ImportMode::FILES);
        $this->seedFileImports(3);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/file-imports?filters[status]='.$statusFilter);

        $this->assertResponseIsSuccessful();
        $this->assertCount($expectedRowCount, $crawler->filter('table.data-table tbody tr'));
        $this->assertCount($expectedFailedCount, $crawler->filter('table.data-table [aria-label="Failed"]'));
        $this->assertCount($expectedSuccessCount, $crawler->filter('table.data-table [aria-label="Success"]'));

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
        yield 'a valid status filter narrows the rows and pre-selects the option' => [
            'failed', 1, 1, 0, 'failed',
        ];

        yield 'an invalid status value is silently ignored' => [
            'bogus', 3, 1, 2, null,
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

    private function seedFileImports(int $count): void
    {
        $fileImportRepository = static::getContainer()->get(FileImportRepository::class);

        for ($i = 1; $i <= $count; ++$i) {
            $failed = 0 === $i % 2;

            $fileImportRepository->add(
                FileImportBuilder::fromDefaults()
                    ->withFileImportId(FileImportId::fromUnprefixed((string) $i))
                    ->withOriginalFilename(sprintf('activity-%d.fit', $i))
                    ->withStatus($failed ? FileImportStatus::FAILED : FileImportStatus::SUCCESS)
                    ->withErrorMessage($failed ? sprintf('Could not parse activity-%d.fit', $i) : null)
                    ->withImportedOn(SerializableDateTime::fromString(sprintf('2026-06-01 %02d:%02d:00', 8 + intdiv($i, 60), $i % 60)))
                    ->build()
            );
        }
    }
}
