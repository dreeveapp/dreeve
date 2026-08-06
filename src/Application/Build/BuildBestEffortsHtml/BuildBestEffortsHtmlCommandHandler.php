<?php

declare(strict_types=1);

namespace App\Application\Build\BuildBestEffortsHtml;

use App\Domain\Activity\BestEffort\BestEffortChart;
use App\Domain\Activity\BestEffort\BestEffortPeriod;
use App\Domain\Activity\BestEffort\BestEffortsCalculator;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Serialization\Json;
use League\Flysystem\FilesystemOperator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class BuildBestEffortsHtmlCommandHandler implements CommandHandler
{
    public function __construct(
        private BestEffortsCalculator $bestEffortsCalculator,
        private TranslatorInterface $translator,
        private Environment $twig,
        private FilesystemOperator $buildHtmlStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildBestEffortsHtml);

        $bestEfforts = $this->bestEffortsCalculator->calculate();
        $bestEffortsCharts = [];

        foreach ($bestEfforts->getActivityTypes() as $activityType) {
            foreach (BestEffortPeriod::cases() as $bestEffortPeriod) {
                $bestEffortsCharts[$activityType->value][$bestEffortPeriod->value] = Json::encode(
                    BestEffortChart::create(
                        activityType: $activityType,
                        period: $bestEffortPeriod,
                        bestEfforts: $bestEfforts,
                        translator: $this->translator,
                    )->build()
                );
            }

            foreach ($activityType->getDistancesForBestEffortCalculation() as $distance) {
                $this->buildHtmlStorage->write(
                    strtolower(sprintf(
                        'best-efforts/%s/%s-%s.html',
                        $activityType->value,
                        $distance->toInt(),
                        $distance->getSymbol()
                    )),
                    $this->twig->load('html/best-efforts/best-efforts-history.html.twig')->render([
                        'activityType' => $activityType,
                        'period' => BestEffortPeriod::ALL_TIME,
                        'distance' => $distance,
                        'bestEfforts' => $bestEfforts,
                    ])
                );
            }
        }

        if ([] === $bestEffortsCharts) {
            return;
        }

        $this->buildHtmlStorage->write(
            'best-efforts.html',
            $this->twig->load('html/best-efforts/best-efforts.html.twig')->render([
                'bestEffortsCharts' => $bestEffortsCharts,
                'bestEfforts' => $bestEfforts,
            ])
        );
    }
}
