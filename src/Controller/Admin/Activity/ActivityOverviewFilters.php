<?php

declare(strict_types=1);

namespace App\Controller\Admin\Activity;

use App\Domain\Activity\ImportSource;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Gear\GearId;
use App\Infrastructure\Http\Request\Filters;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class ActivityOverviewFilters extends Filters
{
    public function isEmpty(): bool
    {
        return !$this->getSportType() instanceof SportType
            && !$this->getGearId() instanceof GearId
            && null === $this->getDevice()
            && !$this->getImportSource() instanceof ImportSource;
    }

    public function getSportType(): ?SportType
    {
        if (null === $sportType = $this->getString('sportType')) {
            return null;
        }

        return SportType::tryFrom($sportType);
    }

    public function getGearId(): ?GearId
    {
        if (null === $gearId = $this->getString('gear')) {
            return null;
        }

        try {
            return GearId::fromString($gearId);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public function getDevice(): ?string
    {
        return $this->getString('device');
    }

    public function getImportSource(): ?ImportSource
    {
        if (null === $importSource = $this->getString('importSource')) {
            return null;
        }

        return ImportSource::tryFrom($importSource);
    }
}
