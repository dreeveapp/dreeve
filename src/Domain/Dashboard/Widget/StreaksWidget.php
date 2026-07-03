<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Activity\EnrichedActivities;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Rewind\FindStreaks\FindStreaks;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Years;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class StreaksWidget implements Widget
{
    public function __construct(
        private TranslatorInterface $translator,
        private EnrichedActivities $enrichedActivities,
        private QueryBus $queryBus,
        private Clock $clock,
        private Environment $twig,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Current streaks');
    }

    public function getTemplateName(): string
    {
        return 'widget--streaks';
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty()
            ->add('sportTypesToInclude', [])
            ->add('subtitle', null);
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
        if (empty($configuration->get('sportTypesToInclude'))) {
            return;
        }

        if (!is_array($configuration->get('sportTypesToInclude'))) {
            throw new InvalidDashboardLayout('Configuration item "sportTypesToInclude" must be an array for StreaksWidget.');
        }

        foreach ($configuration->get('sportTypesToInclude') as $sportTypeToInclude) {
            if (!SportType::tryFrom($sportTypeToInclude)) {
                throw new InvalidDashboardLayout(sprintf('"%s" is not a valid sport type', $sportTypeToInclude));
            }
        }
    }

    public function render(SerializableDateTime $now, WidgetConfiguration $configuration): string
    {
        $sportTypesToInclude = SportTypes::fromArray(array_map(
            SportType::from(...),
            $configuration->get('sportTypesToInclude'),  // @phpstan-ignore argument.type
        ));
        $findStreaksResponse = $this->queryBus->ask(new FindStreaks(
            years: Years::all($this->clock->getCurrentDateTimeImmutable()),
            restrictToSportTypes: $sportTypesToInclude,
        ));

        $activities = $this->enrichedActivities->findAll();
        $mostRecentActivity = $activities->getFirst();
        if (!$sportTypesToInclude->isEmpty()) {
            foreach ($activities as $activity) {
                if (!$sportTypesToInclude->has($activity->getSportType())) {
                    continue;
                }

                $mostRecentActivity = $activity;
                break;
            }
        }

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'subtitle' => $configuration->get('subtitle'),
            'mostRecentActivity' => $mostRecentActivity,
            'dayStreak' => $findStreaksResponse->getCurrentDayStreak(),
            'dayStreakStartDate' => $findStreaksResponse->getCurrentDayStreakStartDate(),
            'weekStreak' => $findStreaksResponse->getCurrentWeekStreak(),
            'weekStreakStartDate' => $findStreaksResponse->getCurrentWeekStreakStartDate(),
            'monthStreak' => $findStreaksResponse->getCurrentMonthStreak(),
            'monthStreakStartDate' => $findStreaksResponse->getCurrentMonthStreakStartDate(),
        ]);
    }
}
