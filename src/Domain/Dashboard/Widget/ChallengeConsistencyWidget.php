<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Activity\EnrichedActivities;
use App\Domain\Calendar\Months;
use App\Domain\Challenge\Consistency\ConsistencyChallengeCalculator;
use App\Domain\Challenge\Consistency\ConsistencyChallenges;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class ChallengeConsistencyWidget implements Widget, HasWideConfigurationForm
{
    public function __construct(
        private TranslatorInterface $translator,
        private EnrichedActivities $enrichedActivities,
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

    public function render(SerializableDateTime $now, WidgetConfiguration $configuration): string
    {
        $allActivities = $this->enrichedActivities->findAll();

        $allMonths = Months::create(
            startDate: $allActivities->getFirstActivityStartDate(),
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
