<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Tool;

use App\Application\AppUrl;
use App\Domain\Activity\Search\ActivitySearchFilters;
use App\Domain\Activity\Search\ActivitySearchRepository;
use App\Domain\Activity\Search\ActivitySearchResult;
use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\Repository\Pagination;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class SearchActivities implements AITool
{
    private const int DEFAULT_SIZE = 25;
    private const int MAX_SIZE = 100;

    public function __construct(
        private ActivitySearchRepository $activitySearchRepository,
        private AppUrl $appUrl,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getName(): string
    {
        return 'search_activities';
    }

    public function getDescription(): string
    {
        return <<<DESC
            Searches the user’s activities, newest first, and returns one compact row per activity: name, sport type, start date, distance, elevation, moving time and whether a GPX file is available.
            Filter by date range, by one or more sport types and by GPX availability. Results are paginated: request the next "page" until "page" equals "totalPages".
            Use this tool when the user asks a question that spans many activities, for example “How many trail runs did I do in 2024?” or “List all my rides over 100 km this year.”
            Use the activity tools with the returned ids for the details of a single activity.
            DESC;
    }

    public function getParameters(): array
    {
        return [
            ToolParameter::string('from', 'Only include activities on or after this date (YYYY-MM-DD).'),
            ToolParameter::string('to', 'Only include activities on or before this date (YYYY-MM-DD).'),
            ToolParameter::string('sportType', 'Comma separated Strava sport types, for example "Ride,VirtualRide".'),
            ToolParameter::boolean('hasGpx', 'Only include activities with (true) or without (false) a GPX file.'),
            ToolParameter::integer('page', 'The page to return, starting at 1.'),
            ToolParameter::integer('size', 'The number of activities per page. Defaults to 25, at most 100.'),
        ];
    }

    public function __invoke(ToolArguments $arguments): array
    {
        $from = $arguments->getDate('from');
        $to = $arguments->getDate('to');
        if ($from && $to && $from->isAfter($to)) {
            throw new ToolFailed('"from" must not be later than "to".');
        }

        $pagination = Pagination::fromPageNumberAndSize(
            pageNumber: max(1, $arguments->getInt('page') ?? 1),
            pageSize: max(1, min($arguments->getInt('size') ?? self::DEFAULT_SIZE, self::MAX_SIZE)),
        );

        $activities = $this->activitySearchRepository->find($pagination, ActivitySearchFilters::fromValues(
            from: $from,
            to: $to,
            sportTypes: null === $arguments->getString('sportType') ? SportTypes::empty() : $arguments->getSportTypes(),
            hasGpx: $arguments->getBool('hasGpx'),
        ));

        return [
            'activities' => array_map(
                fn (ActivitySearchResult $result): array => [
                    'id' => $result->getActivity()->getId()->toUnprefixedString(),
                    'name' => $result->getActivity()->getName(),
                    'sportType' => $result->getActivity()->getSportType()->value,
                    'startDateTime' => $result->getActivity()->getStartDate()->format('Y-m-d H:i:s'),
                    'distanceInKilometer' => round($result->getActivity()->getDistance()->toFloat(), 2),
                    'elevationInMeter' => $result->getActivity()->getElevation()->toInt(),
                    'movingTimeInSeconds' => $result->getActivity()->getMovingTimeInSeconds(),
                    'hasGpx' => $result->hasGpx(),
                    'url' => (string) $this->appUrl->withPath($this->urlGenerator->generate('activity', ['activityId' => (string) $result->getActivity()->getId()])),
                ],
                $activities->getItems(),
            ),
            'page' => $pagination->getCurrentPage(),
            'size' => $pagination->getLimit(),
            'totalActivityCount' => $activities->getTotal(),
            'totalPages' => (int) ceil($activities->getTotal() / $pagination->getLimit()),
        ];
    }
}
