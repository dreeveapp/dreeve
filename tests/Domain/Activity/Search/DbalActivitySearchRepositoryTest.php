<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Search;

use App\Controller\Api\V1\Activity\ActivitySearchFilters;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\DbalActivityRepository;
use App\Domain\Activity\Search\ActivitySearchRepository;
use App\Domain\Activity\Search\ActivitySearchResult;
use App\Domain\Activity\Search\DbalActivitySearchRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\DbalActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Repository\Overview;
use App\Infrastructure\Repository\Pagination;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use App\Tests\Infrastructure\Eventing\SpyEventBus;
use Symfony\Component\HttpFoundation\Request;

class DbalActivitySearchRepositoryTest extends ContainerTestCase
{
    private ActivitySearchRepository $activitySearchRepository;
    private ActivityRepository $activityRepository;
    private ActivityStreamRepository $activityStreamRepository;

    public function testItReturnsTheNewestActivitiesFirst(): void
    {
        $this->provideActivities();

        $this->assertEquals(
            ['activity-300', 'activity-200', 'activity-100'],
            $this->activityIdsIn($this->activitySearchRepository->find(
                Pagination::fromOffsetAndLimit(0, 10),
                $this->filters([]),
            ))
        );
    }

    public function testItIncludesActivitiesStartingExactlyOnTheFromBoundary(): void
    {
        $this->provideActivities();

        $this->assertEquals(
            ['activity-300', 'activity-200'],
            $this->activityIdsIn($this->activitySearchRepository->find(
                Pagination::fromOffsetAndLimit(0, 10),
                $this->filters(['from' => '2026-06-02']),
            ))
        );
    }

    public function testItIncludesActivitiesStartingOnTheToBoundary(): void
    {
        $this->provideActivities();

        $this->assertEquals(
            ['activity-200', 'activity-100'],
            $this->activityIdsIn($this->activitySearchRepository->find(
                Pagination::fromOffsetAndLimit(0, 10),
                $this->filters(['to' => '2026-06-02']),
            ))
        );
    }

    public function testItIncludesAnActivityStartingLateOnTheToBoundaryDay(): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('400'))
                ->withStartDateTime(SerializableDateTime::fromString('2026-06-02 23:59:59'))
                ->build(),
            [],
        ));

        $this->assertEquals(
            ['activity-400'],
            $this->activityIdsIn($this->activitySearchRepository->find(
                Pagination::fromOffsetAndLimit(0, 10),
                $this->filters(['from' => '2026-06-02', 'to' => '2026-06-02']),
            ))
        );
    }

    public function testItFiltersOnASingleCalendarDay(): void
    {
        $this->provideActivities();

        $this->assertEquals(
            ['activity-200'],
            $this->activityIdsIn($this->activitySearchRepository->find(
                Pagination::fromOffsetAndLimit(0, 10),
                $this->filters(['from' => '2026-06-02', 'to' => '2026-06-02']),
            ))
        );
    }

    public function testItFiltersOnSportTypes(): void
    {
        $this->provideActivities();

        $this->assertEquals(
            ['activity-300', 'activity-100'],
            $this->activityIdsIn($this->activitySearchRepository->find(
                Pagination::fromOffsetAndLimit(0, 10),
                $this->filters(['sportType' => 'Run,Swim']),
            ))
        );
    }

    public function testItFiltersOnActivitiesThatHaveGpx(): void
    {
        $this->provideActivities();

        $this->assertEquals(
            ['activity-200'],
            $this->activityIdsIn($this->activitySearchRepository->find(
                Pagination::fromOffsetAndLimit(0, 10),
                $this->filters(['hasGpx' => 'true']),
            ))
        );
    }

    public function testItFiltersOnActivitiesThatHaveNoGpx(): void
    {
        $this->provideActivities();

        $this->assertEquals(
            ['activity-300', 'activity-100'],
            $this->activityIdsIn($this->activitySearchRepository->find(
                Pagination::fromOffsetAndLimit(0, 10),
                $this->filters(['hasGpx' => 'false']),
            ))
        );
    }

    public function testItProjectsTheGpxFlagOnEveryResult(): void
    {
        $this->provideActivities();

        $overview = $this->activitySearchRepository->find(
            Pagination::fromOffsetAndLimit(0, 10),
            $this->filters([]),
        );

        $this->assertEquals(
            [false, true, false],
            array_map(
                static fn (ActivitySearchResult $result): bool => $result->hasGpx(),
                $overview->getItems()
            )
        );
    }

    public function testItReportsTheTotalRegardlessOfThePageSize(): void
    {
        $this->provideActivities();

        $overview = $this->activitySearchRepository->find(
            Pagination::fromPageNumberAndSize(2, 2),
            $this->filters([]),
        );

        $this->assertEquals(3, $overview->getTotal());
        $this->assertEquals(['activity-100'], $this->activityIdsIn($overview));
    }

    public function testItCountsOnlyTheFilteredActivities(): void
    {
        $this->provideActivities();

        $this->assertEquals(1, $this->activitySearchRepository->find(
            Pagination::fromOffsetAndLimit(0, 10),
            $this->filters(['sportType' => 'Run']),
        )->getTotal());
    }

    /**
     * @param Overview<ActivitySearchResult> $overview
     *
     * @return list<string>
     */
    private function activityIdsIn(Overview $overview): array
    {
        return array_map(
            static fn (ActivitySearchResult $result): string => (string) $result->getActivity()->getId(),
            $overview->getItems()
        );
    }

    /**
     * @param array<string, string> $filters
     */
    private function filters(array $filters): ActivitySearchFilters
    {
        return ActivitySearchFilters::fromRequest(new Request(query: ['filters' => $filters]));
    }

    private function provideActivities(): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('100'))
                ->withSportType(SportType::RUN)
                ->withStartDateTime(SerializableDateTime::fromString('2026-06-01 08:00:00'))
                ->build(),
            [],
        ));
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('200'))
                ->withSportType(SportType::RIDE)
                ->withStartDateTime(SerializableDateTime::fromString('2026-06-02 08:00:00'))
                ->build(),
            [],
        ));
        $this->activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('300'))
                ->withSportType(SportType::POOL_SWIM)
                ->withStartDateTime(SerializableDateTime::fromString('2026-06-03 08:00:00'))
                ->build(),
            [],
        ));

        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('200'))
                ->withStreamType(StreamType::TIME)
                ->withData([0, 1, 2])
                ->build()
        );
        $this->activityStreamRepository->add(
            ActivityStreamBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('300'))
                ->withStreamType(StreamType::DISTANCE)
                ->withData([0, 1, 2])
                ->build()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->activityRepository = new DbalActivityRepository(
            $this->getConnection(),
            new SpyEventBus(),
        );
        $this->activityStreamRepository = new DbalActivityStreamRepository(
            $this->getConnection()
        );
        $this->activitySearchRepository = new DbalActivitySearchRepository(
            $this->getConnection()
        );
    }
}
