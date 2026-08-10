<?php

declare(strict_types=1);

namespace App\Application\ConfigureAppColors;

use App\Domain\Activity\SportType\SportTypeRepository;
use App\Domain\Gear\GearRepository;
use App\Domain\Theme\ChartColors;
use App\Domain\Theme\Theme;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;

final readonly class ConfigureAppColorsCommandHandler implements CommandHandler
{
    public function __construct(
        private SportTypeRepository $sportTypeRepository,
        private GearRepository $gearRepository,
        private KeyValueStore $keyValueStore,
        private Theme $theme,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof ConfigureAppColors);

        $chartColors = ChartColors::for(
            $this->sportTypeRepository->findAll(),
            $this->gearRepository->findAllUsed(),
        );

        $this->keyValueStore->save(KeyValue::fromState(
            Key::THEME,
            Value::fromString(Json::encode($chartColors->toMap())),
        ));
        $this->theme->reset();
    }
}
