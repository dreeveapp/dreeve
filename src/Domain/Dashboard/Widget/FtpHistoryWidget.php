<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypeRepository;
use App\Domain\Ftp\FtpHistoryChart;
use App\Domain\Ftp\Ftps;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class FtpHistoryWidget implements Widget
{
    public function __construct(
        private TranslatorInterface $translator,
        private ActivityTypeRepository $activityTypeRepository,
        private Environment $twig,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('FTP history');
    }

    public function getTemplateName(): string
    {
        return 'widget--ftp-history';
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

        $general = $this->settingsRepository->general();
        $ftpHistory = $general->getFtpHistory();
        $athleteWeightHistory = $general->getAthleteWeightHistory($this->settingsRepository->appearance()->getUnitSystem());

        /** @var ActivityType $activityType */
        foreach ($this->activityTypeRepository->findAll() as $activityType) {
            if (!$activityType->supportsPowerData()) {
                continue; // @codeCoverageIgnore
            }

            $allFtps = $ftpHistory->findAll($activityType);
            if ($allFtps->isEmpty()) {
                continue; // @codeCoverageIgnore
            }

            $ftpsEnrichedWithAthleteWeight = Ftps::empty();
            foreach ($allFtps as $ftp) {
                $athleteWeight = null;
                try {
                    $athleteWeight = $athleteWeightHistory->find($ftp->getSetOn())->getWeightInKg();
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

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'ftpHistoryCharts' => $ftpHistoryCharts,
        ]);
    }
}
