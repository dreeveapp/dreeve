<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Activity\Stream\ActivityHeartRateRepository;
use App\Domain\Athlete\HeartRateZone\RelativeHeartRateDistributionChart;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class HeartRateZonesWidget implements Widget
{
    public function __construct(
        private ActivityHeartRateRepository $activityHeartRateRepository,
        private SettingsRepository $settingsRepository,
        private WidgetRenderer $widgetRenderer,
        private TranslatorInterface $translator,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Heart rate zones');
    }

    public function getAdminLabel(): string
    {
        return $this->getLabel();
    }

    public function getTemplateName(): string
    {
        return 'widget--heart-rate-zones';
    }

    public function getCacheTags(): CacheTags
    {
        return CacheTags::of(RootCacheTag::ACTIVITIES, RootCacheTag::SETTINGS_GENERAL);
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty();
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
    }

    public function render(DashboardWidgetId $dashboardWidgetId, SerializableDateTime $now, WidgetConfiguration $configuration): string
    {
        $relativeHeartRateDistribution = $this->activityHeartRateRepository->findRelativeHeartRateDistribution();
        $general = $this->settingsRepository->general();

        return $this->widgetRenderer->render($this, $configuration, [
            'relativeHeartRateDistributionChart' => 0 === $relativeHeartRateDistribution->getTotalTimeInSeconds() ? null : Json::encode(
                RelativeHeartRateDistributionChart::create(
                    relativeHeartRateDistribution: $relativeHeartRateDistribution,
                    heartRateZones: $general->getHeartRateZoneConfiguration()->getDefaultHeartRateZones(null),
                    athleteMaxHeartRate: $general->getAthlete()->getMaxHeartRate($now),
                    translator: $this->translator,
                )->build(),
            ),
        ]);
    }
}
