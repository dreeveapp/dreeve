<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Activity\FindFirstActivityStartDate\FindFirstActivityStartDate;
use App\Domain\Calendar\Months;
use App\Domain\Challenge\Consistency\ConsistencyChallengeCalculator;
use App\Domain\Challenge\Consistency\ConsistencyChallenges;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class ChallengeConsistencyWidget implements Widget, HasWideConfigurationForm
{
    public function __construct(
        private TranslatorInterface $translator,
        private QueryBus $queryBus,
        private ConsistencyChallengeCalculator $consistencyChallengeCalculator,
        private Environment $twig,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Challenge consistency');
    }

    public function getTemplateName(): string
    {
        return 'widget--challenge-consistency';
    }

    public function getCacheTags(): CacheTags
    {
        return CacheTags::of(RootCacheTag::ACTIVITIES, RootCacheTag::CHALLENGES);
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty()
            ->add('challenges', ConsistencyChallenges::getDefaultConfig());
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
        /** @var array<int, mixed> $config */
        $config = $configuration->get('challenges');
        ConsistencyChallenges::fromConfig($config);
    }

    public function render(DashboardWidgetId $dashboardWidgetId, SerializableDateTime $now, WidgetConfiguration $configuration): string
    {
        $allMonths = Months::create(
            startDate: $this->queryBus->ask(new FindFirstActivityStartDate())->getStartDate(),
            endDate: $now
        );
        /** @var array<int, mixed> $config */
        $config = $configuration->get('challenges');
        $consistencyChallenges = ConsistencyChallenges::fromConfig($config);

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'allMonths' => $allMonths,
            'allConsistencyChallenges' => $consistencyChallenges,
            'calculatedConsistencyChallenges' => $this->consistencyChallengeCalculator->calculateFor(
                months: $allMonths,
                challenges: $consistencyChallenges
            ),
        ]);
    }
}
