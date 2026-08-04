<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Settings\AthleteHasNotBeenConfigured;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\FileSystem\PermissionChecker;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValueStore;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AppStatusChecker
{
    public function __construct(
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
        private ActivityIdRepository $activityIdRepository,
        private PermissionChecker $fileSystemPermissionChecker,
        private KeyValueStore $keyValueStore,
    ) {
    }

    public function hasBeenBuilt(): bool
    {
        try {
            $this->keyValueStore->find(Key::APP_LAST_BUILD_SNAPSHOT);
        } catch (EntityNotFound) {
            return false;
        }

        return true;
    }

    public function ensureIsReadyForStravaImport(): void
    {
        $this->ensureFileSystemIsWritable();
    }

    public function ensureIsReadyForFileImport(): void
    {
        $this->ensureFileSystemIsWritable();
    }

    public function ensureIsReadyForBuild(): void
    {
        $this->ensureAthleteCanBeLoaded();

        if ($this->activityIdRepository->count() <= 0) {
            throw AppIsNotReady::becauseNoActivitiesHaveBeenImportedYet();
        }
    }

    private function ensureFileSystemIsWritable(): void
    {
        try {
            $this->fileSystemPermissionChecker->ensureWriteAccess();
        } catch (UnableToWriteFile|UnableToCreateDirectory) {
            throw AppIsNotReady::becauseFileSystemIsNotWritable();
        }
    }

    private function ensureAthleteCanBeLoaded(): void
    {
        try {
            $this->settingsRepository->general();
        } catch (AthleteHasNotBeenConfigured) {
            throw AppIsNotReady::becauseAthleteHasNotBeenConfiguredYet();
        }
    }
}
