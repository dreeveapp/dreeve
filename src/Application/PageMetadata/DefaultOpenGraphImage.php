<?php

declare(strict_types=1);

namespace App\Application\PageMetadata;

use App\Domain\Activity\FindActivityTotals\FindActivityTotals;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Measurement\ProvideMeasurementFormats;
use App\Infrastructure\Measurement\Time\Hour;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
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
        $canvas->drawStats(
            stats: [
                $this->translator->trans('Activities') => $this->formatNumber($activityTotals->getTotalActivities(), 0),
                $this->translator->trans('Distance') => $this->formatUnitWithSymbol(
                    $activityTotals->getTotalDistance()->toUnitSystem($this->settingsRepository->appearance()->getUnitSystem()),
                    0,
                ),
                $this->translator->trans('Moving time') => $this->formatUnitWithSymbol(Hour::from($activityTotals->getMovingTimeInHours()), 0),
            ],
            labelY: 368,
            valueY: 436,
            valueSize: 44,
        );

        return $canvas->toPng();
    }
}
