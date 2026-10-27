<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Activity\ActivityType;
use App\Domain\Activity\ActivityTypeRepository;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Ftp\FtpHistoryChart;
use App\Domain\Ftp\Ftps;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class FtpHistoryWidget implements Widget
{
    public function __construct(
        private TranslatorInterface $translator,
        private ActivityTypeRepository $activityTypeRepository,
        private WidgetRenderer $widgetRenderer,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('FTP history');
    }

    public function getAdminLabel(): string
    {
        return $this->getLabel();
    }

    public function getTemplateName(): string
    {
        return 'widget--ftp-history';
    }

    public function getCacheTags(): CacheTags
    {
        return CacheTags::of(RootCacheTag::SETTINGS_GENERAL, RootCacheTag::SETTINGS_ATHLETE_WEIGHT);
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
        $ftpHistoryCharts = [];

        $ftpHistory = $this->settingsRepository->general()->getFtpHistory();
        $athleteWeightHistory = $this->settingsRepository->athleteWeight()->getHistory($this->settingsRepository->appearance()->getUnitSystem());

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
                now: $now,
                dateFormat: $this->settingsRepository->appearance()->getDateAndTimeFormat()->getDateFormatShort(),
            )->build());
        }

        if ([] === $ftpHistoryCharts) {
            return null;
        }

        return $this->widgetRenderer->render($this, $configuration, [
            'uniqueId' => $dashboardWidgetId->toHtmlIdSuffix(),
            'ftpHistoryCharts' => $ftpHistoryCharts,
        ]);
    }
}
