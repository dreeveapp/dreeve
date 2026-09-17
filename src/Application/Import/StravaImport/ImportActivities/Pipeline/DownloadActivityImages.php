<?php

namespace App\Application\Import\StravaImport\ImportActivities\Pipeline;

use App\Domain\Image\ImagePath;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\Strava;
use App\Infrastructure\ValueObject\Identifier\UuidFactory;
use App\Infrastructure\ValueObject\String\Path;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Uri\Rfc3986\Uri;

#[AsTaggedItem(priority: 10)]
final readonly class DownloadActivityImages implements ActivityImportStep
{
    public function __construct(
        private Strava $strava,
        private FilesystemOperator $fileStorage,
        private UuidFactory $uuidFactory,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function process(ActivityImportContext $context): ActivityImportContext
    {
        $activity = $context->getActivity() ?? throw new \RuntimeException('Activity not set on $context');
        $rawStravaData = $context->getRawStravaData();

        if (!$totalImageCount = ($rawStravaData['total_photo_count'] ?? 0)) {
            return $context->withActivity($activity->withLocalImagePaths([]));
        }

        $shouldDownloadImages = !$this->settingsRepository->import()->shouldSkipImageDownloadDuringImport()
            && ($context->isNewActivity() || count($activity->getLocalImagePaths()) !== $totalImageCount);
        if (!$shouldDownloadImages) {
            return $context->withActivity($context->isNewActivity() ? $activity->withLocalImagePaths([]) : $activity);
        }

        try {
            $fileSystemPaths = [];
            $photos = $this->strava->getActivityPhotos($activity->getId());
            foreach ($photos as $photo) {
                if (empty($photo['urls'][5000])) {
                    continue;
                }

                $uri = new Uri($photo['urls'][5000]);
                $extension = Path::fromString($uri->getPath())->getExtension();

                $fileSystemPath = sprintf('activities/%s.%s', $this->uuidFactory->random(), $extension);
                $this->fileStorage->write(
                    $fileSystemPath,
                    $this->strava->downloadImage($photo['urls'][5000])
                );

                $fileSystemPaths[] = $fileSystemPath;
            }

            $activity = $activity->withLocalImagePaths(array_map(
                fn (string $fileSystemPath): string => ImagePath::fromFileSystemPath($fileSystemPath)->toLocalImagePath(),
                $fileSystemPaths
            ));
        } catch (ClientException|RequestException) {
        }

        return $context->withActivity($activity);
    }
}
