<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Athlete\Weight\AthleteWeightHistoryChart;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class AthleteWeightHistoryWidget implements Widget
{
    public function __construct(
        private Environment $twig,
        private TranslatorInterface $translator,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Weight history');
    }

    public function getTemplateName(): string
    {
        return 'widget--athlete-weight-history';
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
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $allWeights = $this->settingsRepository->general()->getAthleteWeightHistory($unitSystem)->findAll();
        if ([] === $allWeights) {
            return null;
        }

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'athleteWeightHistoryChart' => Json::encode(
                AthleteWeightHistoryChart::create(
                    athleteWeights: $allWeights,
                    now: $now,
                    unitSystem: $unitSystem,
                    translator: $this->translator
                )->build()
            ),
        ]);
    }
}
