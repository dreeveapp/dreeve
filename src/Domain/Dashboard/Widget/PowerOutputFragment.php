<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypeRepository;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Activity\Stream\ActivityPowerRepository;
use App\Domain\Activity\Stream\BestPowerOutputs;
use App\Domain\Activity\Stream\PowerOutputChart;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\Years;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class PowerOutputFragment implements Fragment
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ActivityPowerRepository $activityPowerRepository,
        private ActivityTypeRepository $activityTypeRepository,
        private TranslatorInterface $translator,
        private Clock $clock,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'power-output';
    }

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES),
            ttlInSeconds: $this->clock->getCurrentDateTimeImmutable()->getSecondsUntilMidnight(),
        );
    }

    public function render(): ?string
    {
        $now = $this->clock->getCurrentDateTimeImmutable();

        $activityType = null;
        /** @var ActivityType $importedActivityType */
        foreach ($this->activityTypeRepository->findAll() as $importedActivityType) {
            if (!$importedActivityType->supportsPowerData()) {
                continue; // @codeCoverageIgnore
            }
            $bestAllTimePowerOutputs = $this->activityPowerRepository
                ->findBestForSportTypes(SportTypes::thatSupportPeakPowerOutputs($importedActivityType));

            if ($bestAllTimePowerOutputs->isEmpty()) {
                continue;
            }

            $activityType = $importedActivityType;
            break;
        }

        if (!$activityType instanceof ActivityType) {
            return null;
        }

        $allYears = Years::create(
            startDate: $this->activityRepository->findAll()->getFirstActivityStartDate(),
            endDate: $now
        );

        $bestPowerOutputs = BestPowerOutputs::empty();
        $bestPowerOutputs->add(
            description: $this->translator->trans('All time'),
            powerOutputs: $this->activityPowerRepository->findBestForSportTypes(SportTypes::thatSupportPeakPowerOutputs($activityType)),
        );
        foreach ([45, 90] as $numberOfDays) {
            $bestPowerOutputs->add(
                description: $this->translator->trans('Last {numberOfDays} days', ['{numberOfDays}' => $numberOfDays]),
                powerOutputs: $this->activityPowerRepository->findBestForSportTypesInDateRange(
                    sportTypes: SportTypes::thatSupportPeakPowerOutputs($activityType),
                    dateRange: DateRange::lastXDays($now, $numberOfDays)
                )
            );
        }
        foreach ($allYears->reverse() as $year) {
            $bestPowerOutputs->add(
                description: (string) $year,
                powerOutputs: $this->activityPowerRepository->findBestForSportTypesInDateRange(
                    sportTypes: SportTypes::thatSupportPeakPowerOutputs($activityType),
                    dateRange: $year->getRange(),
                )
            );
        }

        return $this->twig->load('html/dashboard/power-output.html.twig')->render([
            'powerOutputChart' => Json::encode(
                PowerOutputChart::create($bestPowerOutputs)->build()
            ),
            'bestPowerOutputs' => $bestPowerOutputs,
        ]);
    }
}
