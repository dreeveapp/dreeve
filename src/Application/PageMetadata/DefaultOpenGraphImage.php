<?php

declare(strict_types=1);

namespace App\Application\PageMetadata;

use App\Domain\Activity\FindActivityTotals\FindActivityTotals;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Measurement\ProvideMeasurementFormats;
use App\Infrastructure\Measurement\Time\Hour;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class DefaultOpenGraphImage
{
    use ProvideMeasurementFormats;

    public function __construct(
        private QueryBus $queryBus,
        private SettingsRepository $settingsRepository,
        private TranslatorInterface $translator,
        private Client $client,
        private KernelProjectDir $kernelProjectDir,
    ) {
    }

    public function render(): string
    {
        $general = $this->settingsRepository->general();
        $activityTotals = $this->queryBus->ask(new FindActivityTotals());
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
            profilePicture: $profilePicture,
        );
        if (($firstActivityStartDate = $activityTotals->getFirstActivityStartDate()) instanceof SerializableDateTime) {
            $canvas->drawText(
                text: $this->translator->trans('Since {date}', ['{date}' => $firstActivityStartDate->translatedFormat('F Y')]),
                size: 24,
                y: 252,
                weight: 'Regular',
                color: OpenGraphImageCanvas::MUTED,
            );
        }
        $canvas->drawStats(
            stats: [
                $this->translator->trans('Activities') => $this->formatNumber($activityTotals->getTotalActivities(), 0),
                $this->translator->trans('Distance') => $this->formatUnitWithSymbol($activityTotals->getTotalDistance()->toUnitSystem($unitSystem), 0),
            ],
            labelY: 330,
            valueY: 386,
            valueSize: 40,
        );
        $canvas->drawStats(
            stats: [
                $this->translator->trans('Moving time') => $this->formatUnitWithSymbol(Hour::from($activityTotals->getMovingTimeInHours()), 0),
                $this->translator->trans('Elevation') => $this->formatUnitWithSymbol($activityTotals->getTotalElevation()->toUnitSystem($unitSystem), 0),
            ],
            labelY: 460,
            valueY: 516,
            valueSize: 40,
        );

        return $canvas->toPng();
    }
}
