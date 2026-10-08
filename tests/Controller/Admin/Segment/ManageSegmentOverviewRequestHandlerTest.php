<?php

namespace App\Tests\Controller\Admin\Segment;

use App\Domain\Import\ImportMode;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\ValueObject\String\Name;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Segment\SegmentBuilder;

class ManageSegmentOverviewRequestHandlerTest extends AdminWebTestCase
{
    public function testItIsNotAvailableInStravaApiMode(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/segments');

        $this->assertResponseStatusCodeSame(404);
        $this->assertCount(0, $crawler->filter('#drawer-navigation a[href$="/admin/segments"]'));
    }

    public function testRendersTheEmptyState(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/segments');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No segments added yet.', $crawler->filter('body')->text());
        $this->assertCount(0, $crawler->filter('form[method="get"]'));
        $this->assertCount(1, $crawler->filter('#drawer-navigation a[href$="/admin/segments"][aria-selected="true"]'));
        $this->assertCount(1, $crawler->filter('a.btn--add[href$="/admin/segments/add"]'));
    }

    public function testRendersTheTableWithoutTypeFilterWhenThereAreNoImportedSegments(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('1'))
            ->withName(Name::fromString('Kwaremont'))
            ->withType(SegmentType::CUSTOM)
            ->build());
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/segments');

        $this->assertResponseIsSuccessful();
        $this->assertSame(['Kwaremont'], $crawler->filter('table.data-table tbody tr td:first-child')->each(
            static fn ($cell): string => trim($cell->text()),
        ));
        $this->assertCount(1, $crawler->filter('#filter-name'));
        $this->assertCount(0, $crawler->filter('#filter-type'));
        $this->assertCount(1, $crawler->filter('table.data-table tbody a[title="Edit"][href$="/admin/segments/segment-1/edit"]'));
        $this->assertCount(1, $crawler->filter('table.data-table tbody a[title="Delete"][href$="/admin/segments/segment-1/delete"]'));
    }

    public function testFiltersOnTypeAndKeepsTheSelectedValues(): void
    {
        $this->withImportMode(ImportMode::FILES);
        foreach ([['1', 'Kwaremont', SegmentType::IMPORTED], ['2', 'Paterberg', SegmentType::CUSTOM]] as [$id, $name, $type]) {
            $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed($id))
                ->withName(Name::fromString($name))
                ->withType($type)
                ->build());
        }
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/segments?filters[type]=imported&filters[name]=kwa');

        $this->assertResponseIsSuccessful();
        $this->assertSame([['Kwaremont', 'Imported from Strava']], $crawler->filter('table.data-table tbody tr')->each(
            static fn ($row): array => [trim($row->filter('td')->eq(0)->text()), trim($row->filter('td')->eq(1)->text())],
        ));
        $this->assertSame('imported', $crawler->filter('#filter-type option[selected]')->attr('value'));
        $this->assertCount(0, $crawler->filter('table.data-table tbody a[title="Edit"]'));
        $this->assertCount(1, $crawler->filter('table.data-table tbody a[title="Delete"][href$="/admin/segments/segment-1/delete"]'));
        $this->assertSame('kwa', $crawler->filter('#filter-name')->attr('value'));
        $this->assertCount(1, $crawler->filter('a.btn--secondary[href$="/admin/segments"]'));
    }

    public function testRendersTheFilteredEmptyState(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/segments?filters[name]=nothing');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No segments match the current filters.', $crawler->filter('body')->text());
        $this->assertCount(1, $crawler->filter('form[method="get"]'));
    }
}
