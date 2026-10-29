<?php

declare(strict_types=1);

namespace App\Domain\Activity;

use App\Application\PageMetadata\OpenGraphImageCanvas;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Measurement\ProvideMeasurementFormats;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ActivityOpenGraphImage
{
    use ProvideMeasurementFormats;

    public function __construct(
        private SettingsRepository $settingsRepository,
        private TranslatorInterface $translator,
        private Client $client,
        private KernelProjectDir $kernelProjectDir,
    ) {
    }

    public function render(Activity $activity): string
    {
        $general = $this->settingsRepository->general();
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();

        $profilePicture = null;
        if ('' !== ($profilePictureUrl = (string) $general->getProfilePictureUrl())) {
            try {
                $profilePicture = (string) $this->client->get($profilePictureUrl, ['timeout' => 3])->getBody();
            } catch (GuzzleException) {
            }
        }

        $canvas = new OpenGraphImageCanvas($this->kernelProjectDir);
        $canvas->drawHeader(
            athleteName: (string) $general->getAthlete()->getName(),
            profilePicture: $profilePicture
        );
        $canvas->drawText(
            text: sprintf(
                '%s · %s',
                $activity->getSportType()->transSingular($this->translator),
                $activity->getStartDate()->translatedFormat('F j, Y'),
            ),
            size: 24,
            y: 262,
            weight: 'Regular',
            color: OpenGraphImageCanvas::MUTED,
        );
        $canvas->drawText(
            text: $activity->getName(),
            size: 48,
            y: 336,
            weight: 'Bold',
            color: OpenGraphImageCanvas::DARK,
            maxLines: 2,
            lineHeight: 70,
        );
        $canvas->drawStats(
            stats: [
                $this->translator->trans('Distance') => $this->formatUnitWithSymbol(
                    $activity->getDistance()->toUnitSystem($unitSystem),
                    $activity->getSportType()->getActivityType()->getDistancePrecision(),
                ),
                $this->translator->trans('Moving time') => $activity->getMovingTimeFormatted(),
                $this->translator->trans('Elevation') => $this->formatUnitWithSymbol($activity->getElevation()->toUnitSystem($unitSystem), 0),
            ],
            labelY: 486,
            valueY: 550,
            valueSize: 40,
        );

        return $canvas->toPng();
    }
}
