<?php

declare(strict_types=1);

namespace App\Domain\Theme;

use App\Application\ConfigureAppColors\ConfigureAppColors;
use App\Domain\Activity\ActivityWasAdded;
use App\Domain\Activity\ActivityWasDeleted;
use App\Domain\Activity\ActivityWasUpdated;
use App\Domain\Gear\GearWasAdded;
use App\Domain\Gear\GearWasUpdated;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsWereUpdated;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

final class RecalculateChartColorsListener
{
    private bool $chartColorsAreStale = false;

    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
    }

    #[AsEventListener]
    public function reactToActivityWasAdded(ActivityWasAdded $event): void
    {
        $this->chartColorsAreStale = true;
    }

    #[AsEventListener]
    public function reactToActivityWasUpdated(ActivityWasUpdated $event): void
    {
        $this->chartColorsAreStale = true;
    }

    #[AsEventListener]
    public function reactToActivityWasDeleted(ActivityWasDeleted $event): void
    {
        $this->chartColorsAreStale = true;
    }

    #[AsEventListener]
    public function reactToGearWasAdded(GearWasAdded $event): void
    {
        $this->chartColorsAreStale = true;
    }

    #[AsEventListener]
    public function reactToGearWasUpdated(GearWasUpdated $event): void
    {
        $this->chartColorsAreStale = true;
    }

    #[AsEventListener]
    public function reactToSettingsWereUpdated(SettingsWereUpdated $event): void
    {
        // The appearance settings decide in which order sport types are handed their colour.
        if (SettingsGroup::APPEARANCE !== $event->getGroup()) {
            return;
        }

        $this->chartColorsAreStale = true;
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    public function recalculateChartColors(): void
    {
        if (!$this->chartColorsAreStale) {
            return;
        }

        $this->chartColorsAreStale = false;
        $this->commandBus->dispatch(new ConfigureAppColors());
    }
}
