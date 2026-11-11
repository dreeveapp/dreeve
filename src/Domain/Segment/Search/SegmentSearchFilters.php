<?php

declare(strict_types=1);

namespace App\Domain\Segment\Search;

use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\Http\Request\Filters;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class SegmentSearchFilters extends Filters
{
    public function getSportTypes(): SportTypes
    {
        if (null === $sportTypes = $this->getString('sportType')) {
            return SportTypes::empty();
        }

        if (!$sportTypes = SportTypes::tryFromValues(array_map(trim(...), explode(',', $sportTypes)))) {
            throw new BadRequestHttpException('"filters[sportType]" contains an unknown sport type.');
        }

        return $sportTypes;
    }
}
