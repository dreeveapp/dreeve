<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Athlete\Weight\AthleteWeightHistoryChart;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AthleteWeightHistoryWidget implements Widget
{
    public function __construct(
        private WidgetRenderer $widgetRenderer,
        private TranslatorInterface $translator,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Weight history');
    }

    public function getAdminLabel(): string
    {
        return $this->getLabel();
    }

    public function getTemplateName(): string
    {
        return 'widget--athlete-weight-history';
    }

    public function getCacheTags(): CacheTags
    {
        return CacheTags::of(RootCacheTag::SETTINGS_ATHLETE_WEIGHT);
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty();
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
    }

    public function render(DashboardWidgetId $dashboardWidgetId, SerializableDateTime $now, WidgetConfiguration $configuration): ?string
    {
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $allWeights = $this->settingsRepository->athleteWeight()->getHistory($unitSystem)->findAll();
        if ([] === $allWeights) {
            return null;
        }

        return $this->widgetRenderer->render($this, $configuration, [
            'athleteWeightHistoryChart' => Json::encode(
                AthleteWeightHistoryChart::create(
                    athleteWeights: $allWeights,
                    now: $now,
                    unitSystem: $unitSystem,
                    dateFormat: $this->settingsRepository->appearance()->getDateAndTimeFormat()->getDateFormatShort(),
                    translator: $this->translator
                )->build()
            ),
        ]);
    }
}
