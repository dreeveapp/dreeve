<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypeRepository;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Activity\Stream\ActivityPowerRepository;
use App\Domain\Activity\Stream\PowerOutputs;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class PeakPowerOutputsWidget implements Widget, DependsOnCurrentDay
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityPowerRepository $activityPowerRepository,
        private ActivityTypeRepository $activityTypeRepository,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Peak power outputs');
    }

    public function getTemplateName(): string
    {
        return 'widget--peak-power-outputs';
    }

    public function getCacheTags(): CacheTags
    {
        return CacheTags::of(RootCacheTag::ACTIVITIES, RootCacheTag::SETTINGS_METRICS);
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
        /** @var array<string, PowerOutputs> $bestAllTimePowerOutputsPerActivityType */
        $bestAllTimePowerOutputsPerActivityType = [];

        /** @var ActivityType $activityType */
        foreach ($this->activityTypeRepository->findAll() as $activityType) {
            if (!$activityType->supportsPowerData()) {
                continue; // @codeCoverageIgnore
            }
            $bestAllTimePowerOutputs = $this->activityPowerRepository
                ->findBestForSportTypes(SportTypes::thatSupportPeakPowerOutputs($activityType));

            if ($bestAllTimePowerOutputs->isEmpty()) {
                continue;
            }

            $bestAllTimePowerOutputsPerActivityType[$activityType->value] = $bestAllTimePowerOutputs;
        }

        if (empty($bestAllTimePowerOutputsPerActivityType)) {
            return null;
        }

        $activityIds = ActivityIds::empty();
        foreach ($bestAllTimePowerOutputsPerActivityType as $powerOutputs) {
            $activityIds = $activityIds->mergeWith($powerOutputs->getActivityIds());
        }

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'uniqueId' => $dashboardWidgetId->toHtmlIdSuffix(),
            'powerOutputsPerActivityType' => $bestAllTimePowerOutputsPerActivityType,
            'activitiesPerActivityId' => $this->activityRepository->findByIds($activityIds->unique())->keyByActivityId(),
            'timeIntervals' => ActivityPowerRepository::TIME_INTERVALS_IN_SECONDS_REDACTED,
        ]);
    }
}
