<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Activity;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Infrastructure\Http\Request\Filters;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[Exclude]
final readonly class ActivitySearchFilters extends Filters
{
    public const string DATE_FORMAT = '!Y-m-d';

    public function getFrom(): ?SerializableDateTime
    {
        return $this->getDate('from');
    }

    public function getTo(): ?SerializableDateTime
    {
        if (!($to = $this->getDate('to')) instanceof SerializableDateTime) {
            return null;
        }

        if (($from = $this->getDate('from')) && $from->isAfter($to)) {
            throw new BadRequestHttpException('"filters[from]" must not be later than "filters[to]".');
        }

        return $to;
    }

    public function getSportTypes(): SportTypes
    {
        if (null === $sportTypes = $this->getString('sportType')) {
            return SportTypes::empty();
        }

        return SportTypes::fromArray(array_map(
            static function (string $sportType): SportType {
                if (!$sportType = SportType::tryFrom(trim($sportType))) {
                    throw new BadRequestHttpException('"filters[sportType]" contains an unknown sport type.');
                }

                return $sportType;
            },
            explode(',', $sportTypes)
        ));
    }

    public function hasGpx(): ?bool
    {
        if (null === $hasGpx = $this->getString('hasGpx')) {
            return null;
        }

        return match ($hasGpx) {
            'true' => true,
            'false' => false,
            default => throw new BadRequestHttpException('"filters[hasGpx]" must be "true" or "false".'),
        };
    }

    private function getDate(string $name): ?SerializableDateTime
    {
        if (null === $date = $this->getString($name)) {
            return null;
        }

        try {
            return SerializableDateTime::createFromFormat(self::DATE_FORMAT, $date);
        } catch (\InvalidArgumentException) {
            throw new BadRequestHttpException(sprintf('"filters[%s]" must be a date in YYYY-MM-DD format.', $name));
        }
    }
}
