<?php

declare(strict_types=1);

namespace App\Tests\Domain\Segment\Overview;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Segment\Overview\DbalSegmentOverviewRepository;
use App\Domain\Segment\Overview\SegmentOverviewFilters;
use App\Domain\Segment\Overview\SegmentOverviewItem;
use App\Domain\Segment\Overview\SegmentOverviewRepository;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Segment\SegmentType;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\ValueObject\String\Name;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\Domain\Segment\SegmentEffort\SegmentEffortBuilder;
use Symfony\Component\HttpFoundation\Request;

class DbalSegmentOverviewRepositoryTest extends ContainerTestCase
{
    private SegmentOverviewRepository $segmentOverviewRepository;

    public function testFindMapsRowToOverviewItem(): void
    {
        $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('1'))
            ->withName(Name::fromString('Kwaremont'))
            ->withType(SegmentType::CUSTOM)
            ->withSportType(SportType::RIDE)
            ->withDistance(Kilometer::from(2.2))
            ->withAverageGradient(4.2)
            ->build());
        foreach (['1', '2'] as $effortId) {
            $this->getContainer()->get(SegmentEffortRepository::class)->add(SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed($effortId))
                ->withSegmentId(SegmentId::fromUnprefixed('1'))
                ->build());
        }

        $overview = $this->segmentOverviewRepository->find(
            Pagination::fromPageNumberAndSize(1, 25),
            SegmentOverviewFilters::fromRequest(new Request()),
        );

        $this->assertSame(1, $overview->getTotal());
        $this->assertEquals(
            [SegmentOverviewItem::fromState(
                segmentId: SegmentId::fromUnprefixed('1'),
                name: Name::fromString('Kwaremont'),
                type: SegmentType::CUSTOM,
                sportType: SportType::RIDE,
                distance: Kilometer::from(2.2),
                averageGradient: 4.2,
                numberOfEfforts: 2,
            )],
            $overview->getItems(),
        );
    }

    public function testFindListsCustomSegmentsFirstAndSortsByName(): void
    {
        foreach ([
            ['1', 'A imported', SegmentType::IMPORTED],
            ['2', 'B custom', SegmentType::CUSTOM],
            ['3', 'C imported', SegmentType::IMPORTED],
            ['4', 'A custom', SegmentType::CUSTOM],
        ] as [$id, $name, $type]) {
            $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed($id))
                ->withName(Name::fromString($name))
                ->withType($type)
                ->build());
        }

        $this->assertSame(
            ['A custom', 'B custom', 'A imported', 'C imported'],
            $this->findNames(new Request()),
        );
    }

    public function testFindFiltersByTypeAndName(): void
    {
        foreach ([
            ['1', 'Kwaremont', SegmentType::IMPORTED],
            ['2', 'Paterberg', SegmentType::IMPORTED],
            ['3', 'Oude Kwaremont', SegmentType::CUSTOM],
            ['4', '100%_climb', SegmentType::CUSTOM],
        ] as [$id, $name, $type]) {
            $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed($id))
                ->withName(Name::fromString($name))
                ->withType($type)
                ->build());
        }

        $this->assertSame(['Kwaremont', 'Paterberg'], $this->findNames(new Request(query: ['filters' => ['type' => 'imported']])));
        $this->assertSame(['Oude Kwaremont', 'Kwaremont'], $this->findNames(new Request(query: ['filters' => ['name' => 'kwaremont']])));
        $this->assertSame(['Oude Kwaremont'], $this->findNames(new Request(query: ['filters' => ['name' => 'kwaremont', 'type' => 'custom']])));
        $this->assertSame(['100%_climb'], $this->findNames(new Request(query: ['filters' => ['name' => '%_']])));
    }

    public function testFindPaginates(): void
    {
        foreach (range(1, 3) as $id) {
            $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed((string) $id))
                ->withName(Name::fromString('Segment '.$id))
                ->build());
        }

        $overview = $this->segmentOverviewRepository->find(
            Pagination::fromPageNumberAndSize(2, 2),
            SegmentOverviewFilters::fromRequest(new Request()),
        );

        $this->assertSame(3, $overview->getTotal());
        $this->assertSame(['Segment 3'], array_map(
            static fn (SegmentOverviewItem $item): string => (string) $item->getName(),
            $overview->getItems(),
        ));
    }

    public function testCountByType(): void
    {
        foreach ([['1', SegmentType::IMPORTED], ['2', SegmentType::IMPORTED], ['3', SegmentType::CUSTOM]] as [$id, $type]) {
            $this->getContainer()->get(SegmentRepository::class)->add(SegmentBuilder::fromDefaults()
                ->withSegmentId(SegmentId::fromUnprefixed($id))
                ->withType($type)
                ->build());
        }

        $this->assertSame(2, $this->segmentOverviewRepository->countByType(SegmentType::IMPORTED));
        $this->assertSame(1, $this->segmentOverviewRepository->countByType(SegmentType::CUSTOM));
    }

    /**
     * @return list<string>
     */
    private function findNames(Request $request): array
    {
        return array_map(
            static fn (SegmentOverviewItem $item): string => (string) $item->getName(),
            $this->segmentOverviewRepository->find(
                Pagination::fromPageNumberAndSize(1, 25),
                SegmentOverviewFilters::fromRequest($request),
            )->getItems(),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->segmentOverviewRepository = new DbalSegmentOverviewRepository($this->getConnection());
    }
}
