<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Athlete\Weight\AthleteWeightHistory;
use App\Domain\Athlete\Weight\AthleteWeightHistoryChart;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class AthleteWeightHistoryWidget implements Widget
{
    public function __construct(
        private AthleteWeightHistory $athleteWeightHistory,
        private UnitSystem $unitSystem,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Weight history');
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty();
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
    }

    public function render(SerializableDateTime $now, WidgetConfiguration $configuration): ?string
    {
        $allWeights = $this->athleteWeightHistory->findAll();
        if ([] === $allWeights) {
            return null;
        }

        return $this->twig->load('html/dashboard/widget/widget--athlete-weight-history.html.twig')->render([
            'athleteWeightHistoryChart' => Json::encode(
                AthleteWeightHistoryChart::create(
                    athleteWeights: $allWeights,
                    now: $now,
                    unitSystem: $this->unitSystem,
                    translator: $this->translator
                )->build()
            ),
        ]);
    }
}
