<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Activity\ActivityTypeRepository;
use App\Domain\Activity\Stream\ActivityHeartRateRepository;
use App\Domain\Athlete\HeartRateZone\TimeInHeartRateZoneChart;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class HeartRateZonesWidget implements Widget
{
    public function __construct(
        private ActivityHeartRateRepository $activityHeartRateRepository,
        private ActivityTypeRepository $activityTypeRepository,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Heart rate zones');
    }

    public function getTemplateName(): string
    {
        return 'widget--heart-rate-zones';
    }

    public function getCacheTags(): CacheTags
    {
        return CacheTags::of(RootCacheTag::ACTIVITIES);
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
        $chartsPerActivityType = [];
        $importedActivityTypes = $this->activityTypeRepository->findAll();

        /* @var \App\Domain\Activity\ActivityType $activityType */
        if (count($importedActivityTypes) > 1) {
            $timeInHeartRateZonesPerActivityType = $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZonesPerActivityType();
            foreach ($importedActivityTypes as $activityType) {
                $chartsPerActivityType[$activityType->value] = Json::encode(
                    TimeInHeartRateZoneChart::create(
                        timeInHeartRateZones: $timeInHeartRateZonesPerActivityType[$activityType->value],
                        translator: $this->translator,
                    )->build(),
                );
            }
        }

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'uniqueId' => $dashboardWidgetId->toHtmlIdSuffix(),
            'timeInHeartRateZoneChart' => Json::encode(
                TimeInHeartRateZoneChart::create(
                    timeInHeartRateZones: $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZones(),
                    translator: $this->translator,
                )->build(),
            ),
            'chartsPerActivityType' => $chartsPerActivityType,
        ]);
    }
}
