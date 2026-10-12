<?php

declare(strict_types=1);

namespace App\Controller\Api\V1\Gear;

use App\Domain\Gear\GearId;
use App\Infrastructure\Http\Request\Filters;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[Exclude]
final readonly class GearMaintenanceTaskSearchFilters extends Filters
{
    public function getGearId(): ?GearId
    {
        if (null === $gearId = $this->getString('gearId')) {
            return null;
        }

        if (!str_starts_with($gearId, GearId::getPrefix())) {
            throw new BadRequestHttpException('"filters[gearId]" is not a valid gear id.');
        }

        return GearId::fromString($gearId);
    }
}
