<?php

declare(strict_types=1);

namespace App\Domain\Segment\Search;

use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\Http\Request\Filters;
use App\Infrastructure\ValueObject\Geography\BoundingBox;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class SegmentSearchFilters extends Filters
{
    public function getSportTypes(): SportTypes
    {
        if (null === $sportTypes = $this->getString('sportType')) {
            return SportTypes::empty();
        }

        if (!($sportTypes = SportTypes::tryFromValues(array_map(trim(...), explode(',', $sportTypes)))) instanceof SportTypes) {
            throw new BadRequestHttpException('"filters[sportType]" contains an unknown sport type.');
        }

        return $sportTypes;
    }

    public function getBounds(): ?BoundingBox
    {
        if (null === $bounds = $this->getString('bounds')) {
            return null;
        }

        $values = explode(',', $bounds);
        if (4 !== count($values)) {
            throw new BadRequestHttpException('"filters[bounds]" must be "south,west,north,east" in decimal degrees.');
        }

        try {
            [$south, $west, $north, $east] = [
                Latitude::fromString($values[0])->toFloat(),
                Longitude::fromString($values[1])->toFloat(),
                Latitude::fromString($values[2])->toFloat(),
                Longitude::fromString($values[3])->toFloat(),
            ];
        } catch (\InvalidArgumentException) {
            throw new BadRequestHttpException('"filters[bounds]" must be "south,west,north,east" in decimal degrees.');
        }

        if ($south > $north) {
            throw new BadRequestHttpException('"filters[bounds]" south must not be greater than north.');
        }

        return BoundingBox::fromArray([$west, $south, $east, $north]);
    }
}
