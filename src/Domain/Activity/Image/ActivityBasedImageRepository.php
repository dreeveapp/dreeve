<?php

namespace App\Domain\Activity\Image;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Image\ImageOrientation;
use App\Domain\Image\ImagePath;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Infrastructure\ValueObject\Time\Year;
use App\Infrastructure\ValueObject\Time\Years;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;

final readonly class ActivityBasedImageRepository implements ImageRepository
{
    public function __construct(
        private Connection $connection,
        private ActivityRepository $activityRepository,
        private FilesystemOperator $fileStorage,
        private SettingsRepository $settingsRepository,
        private KernelProjectDir $kernelProjectDir,
    ) {
    }

    public function findAll(): Images
    {
        $images = Images::empty();
        $activities = $this->activityRepository->findAll();
        foreach ($activities as $activity) {
            if (0 === $activity->getTotalImageCount()) {
                continue;
            }

            if ($this->settingsRepository->appearance()->getHidePhotosForSportTypes()->has($activity->getSportType())) {
                continue;
            }

            foreach ($activity->getLocalImagePaths() as $localImagePath) {
                $absoluteImagePath = $this->kernelProjectDir.'/storage'.$localImagePath;
                $imageOrientation = ImageOrientation::LANDSCAPE;
                if (file_exists($absoluteImagePath) && ($info = @getimagesize($absoluteImagePath)) !== false) {
                    $imageOrientation = ImageOrientation::fromWidthAndHeight($info[0], $info[1]);
                }

                $images->add(Image::create(
                    imageLocation: $localImagePath,
                    activityId: $activity->getId(),
                    sportType: $activity->getSportType(),
                    orientation: $imageOrientation,
                    relatedCountryCodes: $activity->getRouteGeography()->getPassedThroughCountries(),
                ));
            }
        }

        return $images;
    }

    public function findRandomFor(SportTypes $sportTypes, Years $years): Image
    {
        $activities = $this->activityRepository->findAll()->toArray();
        shuffle($activities);

        foreach ($activities as $activity) {
            if (!$years->has(Year::fromInt($activity->getStartDate()->getYear()))) {
                continue;
            }

            if (!$sportTypes->has($activity->getSportType())) {
                continue;
            }

            if (!$localImagePaths = $activity->getLocalImagePaths()) {
                continue;
            }

            $randomImageIndex = array_rand($localImagePaths);

            $absoluteImagePath = $this->kernelProjectDir.'/storage'.$localImagePaths[$randomImageIndex];
            $imageOrientation = ImageOrientation::LANDSCAPE;
            if (file_exists($absoluteImagePath) && ($info = @getimagesize($absoluteImagePath)) !== false) {
                $imageOrientation = ImageOrientation::fromWidthAndHeight($info[0], $info[1]);
            }

            return Image::create(
                imageLocation: $localImagePaths[$randomImageIndex],
                activityId: $activity->getId(),
                sportType: $activity->getSportType(),
                orientation: $imageOrientation,
                relatedCountryCodes: $activity->getRouteGeography()->getPassedThroughCountries(),
            );
        }

        throw new EntityNotFound('Random image not found');
    }

    public function count(): int
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('SUM(totalImageCount)')
            ->from('Activity');

        $hiddenSportTypes = $this->settingsRepository->appearance()->getHidePhotosForSportTypes();
        if (!$hiddenSportTypes->isEmpty()) {
            $queryBuilder->andWhere('sportType NOT IN (:hiddenSportTypes)')
                ->setParameter(
                    'hiddenSportTypes',
                    array_map(fn (SportType $sportType): string => $sportType->value, $hiddenSportTypes->toArray()),
                    ArrayParameterType::STRING
                );
        }

        return (int) $queryBuilder->executeQuery()->fetchOne();
    }

    public function deleteForActivity(ActivityId $activityId): void
    {
        try {
            $activity = $this->activityRepository->find($activityId);
        } catch (EntityNotFound) {
            return;
        }

        foreach ($activity->getLocalImagePaths() as $localImagePath) {
            $fileSystemPath = ImagePath::fromLocalImagePath($localImagePath)->toFileSystemPath();
            if ($this->fileStorage->fileExists($fileSystemPath)) {
                $this->fileStorage->delete($fileSystemPath);
            }
        }
    }
}
