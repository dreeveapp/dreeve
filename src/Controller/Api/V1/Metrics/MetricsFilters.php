<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Metrics;

use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\Http\Request\Filters;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[Exclude]
final readonly class MetricsFilters extends Filters
{
    public const string DATE_FORMAT = '!Y-m-d';

    public function getFrom(): SerializableDateTime
    {
        return $this->getRequiredDate('from');
    }

    public function getTo(): SerializableDateTime
    {
        $to = $this->getRequiredDate('to');

        if ($this->getRequiredDate('from')->isAfter($to)) {
            throw new BadRequestHttpException('"filters[from]" must not be later than "filters[to]".');
        }

        return $to;
    }

    public function getSportTypes(): SportTypes
    {
        if (null === $sportTypes = $this->getString('sportType')) {
            return SportTypes::all();
        }

        if (!($sportTypes = SportTypes::tryFromValues(array_map(trim(...), explode(',', $sportTypes)))) instanceof SportTypes) {
            throw new BadRequestHttpException('"filters[sportType]" contains an unknown sport type.');
        }

        return $sportTypes;
    }

    private function getRequiredDate(string $name): SerializableDateTime
    {
        if (null === $date = $this->getString($name)) {
            throw new BadRequestHttpException(sprintf('"filters[%s]" is required.', $name));
        }

        try {
            return SerializableDateTime::createFromFormat(self::DATE_FORMAT, $date);
        } catch (\InvalidArgumentException) {
            throw new BadRequestHttpException(sprintf('"filters[%s]" must be a date in YYYY-MM-DD format.', $name));
        }
    }
}
