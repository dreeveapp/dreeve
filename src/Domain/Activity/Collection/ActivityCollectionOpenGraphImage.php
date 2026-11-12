<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection;

use App\Application\PageMetadata\OpenGraphImageCanvas;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Measurement\ProvideMeasurementFormats;
use App\Infrastructure\Time\Format\ProvideTimeFormats;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ActivityCollectionOpenGraphImage
{
    use ProvideMeasurementFormats;
    use ProvideTimeFormats;

    public function __construct(
        private SettingsRepository $settingsRepository,
        private TranslatorInterface $translator,
        private Client $client,
        private KernelProjectDir $kernelProjectDir,
    ) {
    }

    public function render(ActivityCollectionWithActivities $activityCollectionWithActivities): string
    {
        $general = $this->settingsRepository->general();
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $activityCollection = $activityCollectionWithActivities->getActivityCollection();

        $profilePicture = null;
        if ('' !== ($profilePictureUrl = (string) $general->getProfilePictureUrl())) {
            try {
                $profilePicture = (string) $this->client->get($profilePictureUrl, ['timeout' => 3])->getBody();
            } catch (GuzzleException) {
            }
        }

        $date = $activityCollectionWithActivities->getStartDate()->translatedFormat('F j, Y');
        if ($activityCollectionWithActivities->getNumberOfDays() > 1) {
            $date .= ' - '.$activityCollectionWithActivities->getEndDate()->translatedFormat('F j, Y');
        }

        $canvas = new OpenGraphImageCanvas($this->kernelProjectDir);
        $canvas->drawHeader(
            athleteName: (string) $general->getAthlete()->getName(),
            profilePicture: $profilePicture
        );
        $canvas->drawText(
            text: sprintf(
                '%s · %s',
                $activityCollection->getType()->trans($this->translator),
                $date,
            ),
            size: 24,
            y: 262,
            weight: 'Regular',
            color: OpenGraphImageCanvas::MUTED,
        );
        $canvas->drawText(
            text: $activityCollection->getName(),
            size: 48,
            y: 336,
            weight: 'Bold',
            color: OpenGraphImageCanvas::DARK,
            maxLines: 2,
            lineHeight: 70,
        );
        $canvas->drawStats(
            stats: [
                $this->translator->trans('Activities') => $this->formatNumber(count($activityCollectionWithActivities->getActivities()), 0),
                $this->translator->trans('Distance') => $this->formatUnitWithSymbol($activityCollectionWithActivities->getDistance()->toUnitSystem($unitSystem), 0),
                $this->translator->trans('Moving time') => $this->formatDurationAsHumanString($activityCollectionWithActivities->getMovingTime()->toInt()),
                $this->translator->trans('Elevation') => $this->formatUnitWithSymbol($activityCollectionWithActivities->getElevation()->toUnitSystem($unitSystem), 0),
            ],
            labelY: 486,
            valueY: 550,
            valueSize: 30,
        );

        return $canvas->toPng();
    }
}
