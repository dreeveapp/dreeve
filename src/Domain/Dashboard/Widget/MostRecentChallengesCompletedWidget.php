<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Challenge\ChallengeRepository;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class MostRecentChallengesCompletedWidget implements Widget
{
    public function __construct(
        private TranslatorInterface $translator,
        private ChallengeRepository $challengeRepository,
        private Environment $twig,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Most recent challenges');
    }

    public function getTemplateName(): string
    {
        return 'widget--most-recent-challenges';
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty()
            ->add('numberOfChallengesToDisplay', 5);
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
        if (!$configuration->exists('numberOfChallengesToDisplay')) {
            throw new InvalidDashboardLayout('Configuration item "numberOfChallengesToDisplay" is required for MostRecentChallengesCompletedWidget.');
        }
        if (!is_int($configuration->get('numberOfChallengesToDisplay'))) {
            throw new InvalidDashboardLayout('Configuration item "numberOfChallengesToDisplay" must be an integer.');
        }
        if ($configuration->get('numberOfChallengesToDisplay') < 1) {
            throw new InvalidDashboardLayout('Configuration item "numberOfChallengesToDisplay" must be set to a value of 1 or greater.');
        }
    }

    public function render(SerializableDateTime $now, WidgetConfiguration $configuration): ?string
    {
        $challenges = $this->challengeRepository->findAll();
        if ($challenges->isEmpty()) {
            return null;
        }

        $numberOfChallengesToDisplay = (int) $configuration->get('numberOfChallengesToDisplay');

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'challenges' => $challenges->slice(0, $numberOfChallengesToDisplay),
        ]);
    }
}
