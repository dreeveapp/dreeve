<?php

declare(strict_types=1);

namespace App\Application\Build\BuildBadgeSvg;

use App\Application\AppUrl;
use App\Domain\Activity\ActivityTotals;
use App\Domain\Activity\ActivityType;
use App\Domain\Activity\BestEffort\BestEffortPeriod;
use App\Domain\Activity\BestEffort\BestEffortsCalculator;
use App\Domain\Activity\EnrichedActivities;
use App\Domain\Challenge\ChallengeRepository;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Zwift\ZwiftLevel;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use League\Flysystem\FilesystemOperator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class BuildBadgeSvgCommandHandler implements CommandHandler
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private ChallengeRepository $challengeRepository,
        private EnrichedActivities $enrichedActivities,
        private BestEffortsCalculator $bestEffortsCalculator,
        private AppUrl $appUrl,
        private Environment $twig,
        private FilesystemOperator $fileStorage,
        private FilesystemOperator $buildHtmlStorage,
        private TranslatorInterface $translator,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildBadgeSvg);

        $now = $command->getCurrentDateTime();
        $athlete = $this->settingsRepository->general()->getAthlete();
        $zwiftLevel = $this->settingsRepository->zwift()->getZwiftLevel();
        $zwiftRacingScore = $this->settingsRepository->zwift()->getZwiftRacingScore();
        $activities = $this->enrichedActivities->findAll();

        $activityTotals = ActivityTotals::getInstance(
            activities: $activities,
            now: $now,
            translator: $this->translator,
        );

        $this->fileStorage->write(
            'strava-badge.svg',
            $this->twig->load('svg/badge/svg-strava-badge.html.twig')->render([
                'athlete' => $athlete,
                'activities' => $activities->slice(0, 5),
                'activityTotals' => $activityTotals,
                'challengesCompleted' => $this->challengeRepository->count(),
            ])
        );

        if ($zwiftLevel instanceof ZwiftLevel) {
            $this->fileStorage->write(
                'zwift-badge.svg',
                $this->twig->load('svg/badge/svg-zwift-badge.html.twig')->render([
                    'athlete' => $athlete,
                    'zwiftLevel' => $zwiftLevel,
                    'zwiftRacingScore' => $zwiftRacingScore,
                ])
            );
        }

        $sportTypesThatHaveBestEfforts = [];
        /** @var ActivityType $activityType */
        foreach ($this->bestEffortsCalculator->getActivityTypes() as $activityType) {
            $sportTypes = $this->bestEffortsCalculator->getSportTypesFor(
                period: BestEffortPeriod::ALL_TIME,
                activityType: $activityType,
            );
            foreach ($sportTypes as $sportType) {
                $sportTypesThatHaveBestEfforts[] = $sportType;

                $this->fileStorage->write(
                    strtolower(sprintf('pb-%s-badge.svg', $sportType->value)),
                    $this->twig->load('svg/badge/svg-pb-badge.html.twig')->render([
                        'sportType' => $sportType,
                        'period' => BestEffortPeriod::ALL_TIME,
                    ])
                );
            }
        }

        $this->buildHtmlStorage->write(
            'badge.html',
            $this->twig->load('html/badges.html.twig')->render([
                'zwiftLevel' => $zwiftLevel,
                'appUrl' => rtrim((string) $this->appUrl, '/'),
                'sportTypesThatHaveBestEfforts' => $sportTypesThatHaveBestEfforts,
            ]),
        );
    }
}
