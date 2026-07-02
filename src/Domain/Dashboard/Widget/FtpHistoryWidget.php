<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypeRepository;
use App\Domain\Athlete\Weight\AthleteWeightHistory;
use App\Domain\Ftp\FtpHistory;
use App\Domain\Ftp\FtpHistoryChart;
use App\Domain\Ftp\Ftps;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class FtpHistoryWidget implements Widget
{
    public function __construct(
        private TranslatorInterface $translator,
        private FtpHistory $ftpHistory,
        private AthleteWeightHistory $athleteWeightHistory,
        private ActivityTypeRepository $activityTypeRepository,
        private Environment $twig,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('FTP history');
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty();
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
    }

    public function render(SerializableDateTime $now, WidgetConfiguration $configuration): ?string
    {
        $ftpHistoryCharts = [];

        /** @var ActivityType $activityType */
        foreach ($this->activityTypeRepository->findAll() as $activityType) {
            if (!$activityType->supportsPowerData()) {
                continue; // @codeCoverageIgnore
            }

            $allFtps = $this->ftpHistory->findAll($activityType);
            if ($allFtps->isEmpty()) {
                continue; // @codeCoverageIgnore
            }

            $ftpsEnrichedWithAthleteWeight = Ftps::empty();
            foreach ($allFtps as $ftp) {
                $athleteWeight = null;
                try {
                    $athleteWeight = $this->athleteWeightHistory->find($ftp->getSetOn())->getWeightInKg();
                } catch (EntityNotFound) { // @codeCoverageIgnore
                }
                $ftpsEnrichedWithAthleteWeight->add($ftp->withAthleteWeight($athleteWeight));
            }

            $ftpHistoryCharts[$activityType->value] = Json::encode(FtpHistoryChart::create(
                ftps: $ftpsEnrichedWithAthleteWeight,
                now: $now
            )->build());
        }

        if ([] === $ftpHistoryCharts) {
            return null;
        }

        return $this->twig->load('html/dashboard/widget/widget--ftp-history.html.twig')->render([
            'ftpHistoryCharts' => $ftpHistoryCharts,
        ]);
    }
}
