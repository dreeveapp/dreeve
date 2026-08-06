<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\AppIsNotReady;
use App\Application\AppStatusChecker;
use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Infrastructure\FileSystem\SuccessfulPermissionChecker;
use App\Tests\Infrastructure\FileSystem\UnwritablePermissionChecker;

class AppStatusCheckerTest extends ContainerTestCase
{
    public function testEnsureIsReadyForStravaImportPasses(): void
    {
        $this->expectNotToPerformAssertions();

        new AppStatusChecker(
            $this->getContainer()->get(SettingsRepository::class),
            $this->getContainer()->get(ActivityIdRepository::class),
            new SuccessfulPermissionChecker(),
            $this->getContainer()->get(KeyValueStore::class),
        )->ensureIsReadyForStravaImport();
    }

    public function testEnsureIsReadyForStravaImportThrowsWhenFileSystemIsNotWritable(): void
    {
        $this->expectExceptionObject(AppIsNotReady::becauseFileSystemIsNotWritable());

        new AppStatusChecker(
            $this->getContainer()->get(SettingsRepository::class),
            $this->getContainer()->get(ActivityIdRepository::class),
            new UnwritablePermissionChecker(),
            $this->getContainer()->get(KeyValueStore::class),
        )->ensureIsReadyForStravaImport();
    }

    public function testEnsureIsReadyForFileImportPasses(): void
    {
        $this->expectNotToPerformAssertions();

        new AppStatusChecker(
            $this->getContainer()->get(SettingsRepository::class),
            $this->getContainer()->get(ActivityIdRepository::class),
            new SuccessfulPermissionChecker(),
            $this->getContainer()->get(KeyValueStore::class),
        )->ensureIsReadyForFileImport();
    }

    public function testEnsureIsReadyForFileImportThrowsWhenFileSystemIsNotWritable(): void
    {
        $this->expectExceptionObject(AppIsNotReady::becauseFileSystemIsNotWritable());

        new AppStatusChecker(
            $this->getContainer()->get(SettingsRepository::class),
            $this->getContainer()->get(ActivityIdRepository::class),
            new UnwritablePermissionChecker(),
            $this->getContainer()->get(KeyValueStore::class),
        )->ensureIsReadyForFileImport();
    }

    public function testEnsureIsReadyForBuildPasses(): void
    {
        $this->expectNotToPerformAssertions();

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->build(),
            [],
        ));

        new AppStatusChecker(
            $this->getContainer()->get(SettingsRepository::class),
            $this->getContainer()->get(ActivityIdRepository::class),
            new SuccessfulPermissionChecker(),
            $this->getContainer()->get(KeyValueStore::class),
        )->ensureIsReadyForBuild();
    }

    public function testEnsureIsReadyForBuildThrowsWhenAthleteHasNotBeenConfigured(): void
    {
        // Clear the general settings so the athlete cannot be loaded.
        $this->getContainer()->get(SettingsRepository::class)->save(SettingsGroup::GENERAL, []);

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->build(),
            [],
        ));

        $this->expectExceptionObject(AppIsNotReady::becauseAthleteHasNotBeenConfiguredYet());

        new AppStatusChecker(
            $this->getContainer()->get(SettingsRepository::class),
            $this->getContainer()->get(ActivityIdRepository::class),
            new SuccessfulPermissionChecker(),
            $this->getContainer()->get(KeyValueStore::class),
        )->ensureIsReadyForBuild();
    }

    public function testEnsureIsReadyForBuildThrowsWhenNoActivitiesHaveBeenImported(): void
    {
        $this->expectExceptionObject(AppIsNotReady::becauseNoActivitiesHaveBeenImportedYet());

        new AppStatusChecker(
            $this->getContainer()->get(SettingsRepository::class),
            $this->getContainer()->get(ActivityIdRepository::class),
            new SuccessfulPermissionChecker(),
            $this->getContainer()->get(KeyValueStore::class),
        )->ensureIsReadyForBuild();
    }
}
