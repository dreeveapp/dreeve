<?php

declare(strict_types=1);

namespace App\Application\Build\BuildEddingtonHtml;

use App\Domain\Activity\Eddington\EddingtonCalculator;
use App\Domain\Activity\Eddington\EddingtonChart;
use App\Domain\Activity\Eddington\EddingtonHistoryChart;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use League\Flysystem\FilesystemOperator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class BuildEddingtonHtmlCommandHandler implements CommandHandler
{
    public function __construct(
        private EddingtonCalculator $eddingtonCalculator,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
        private FilesystemOperator $buildHtmlStorage,
        private TranslatorInterface $translator,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildEddingtonHtml);

        $eddingtonCharts = [];
        $eddingtonHistoryCharts = [];
        $allEddingtons = [];

        foreach (UnitSystem::cases() as $unitSystem) {
            $allEddingtons = [...$allEddingtons, ...$this->eddingtonCalculator->calculate($unitSystem)];
        }

        foreach ($allEddingtons as $eddington) {
            $id = $eddington->getId();
            $eddingtonCharts[$id] = Json::encode(
                EddingtonChart::create(
                    eddington: $eddington,
                    unitSystem: $eddington->getUnitSystem(),
                    translator: $this->translator,
                )->build()
            );
            $eddingtonHistoryCharts[$id] = Json::encode(
                EddingtonHistoryChart::create(
                    eddington: $eddington,
                )->build()
            );
        }

        $this->buildHtmlStorage->write(
            'eddington.html',
            $this->twig->load('html/eddington.html.twig')->render([
                'activeUnitSystem' => $this->settingsRepository->appearance()->getUnitSystem(),
                'eddingtons' => $allEddingtons,
                'eddingtonCharts' => $eddingtonCharts,
                'eddingtonHistoryCharts' => $eddingtonHistoryCharts,
            ]),
        );
    }
}
