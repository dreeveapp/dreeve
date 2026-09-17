<?php

namespace App\Tests\Application\Import\StravaImport\ImportActivities\Pipeline;

use App\Application\Import\StravaImport\ImportActivities\Pipeline\ActivityImportContext;
use App\Application\Import\StravaImport\ImportActivities\Pipeline\DownloadActivityImages;
use App\Domain\Activity\ActivityId;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\Strava;
use App\Infrastructure\ValueObject\Identifier\UuidFactory;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Strava\SpyStrava;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;

class DownloadActivityImagesTest extends ContainerTestCase
{
    private DownloadActivityImages $downloadActivityImages;
    private SpyStrava $strava;
    private Filesystem $fileStorage;
    private SettingsRepository $settingsRepository;

    public function testProcessWhenImagesShouldNotBeDownloaded(): void
    {
        $context = ActivityImportContext::create(
            activityId: ActivityId::fromUnprefixed(1),
            rawStravaData: ['total_photo_count' => 3],
            isNewActivity: false,
        )
            ->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withLocalImagePaths('one', 'two', 'three')
                    ->build()
            );

        $this->assertEquals(
            $context,
            $this->downloadActivityImages->process($context)
        );
    }

    public function testProcessWhenImageDownloadIsSkippedForANewActivity(): void
    {
        $this->settingsRepository->saveGroup(SettingsGroup::IMPORT, ['skipImageDownloadDuringImport' => true]);

        $context = ActivityImportContext::create(
            activityId: ActivityId::fromUnprefixed(1),
            rawStravaData: ['total_photo_count' => 3],
            isNewActivity: true,
        )
            ->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withTotalImageCount(3)
                    ->build()
            );

        $activity = $this->downloadActivityImages->process($context)->getActivity();

        $this->assertSame(0, $activity->getTotalImageCount());
        $this->assertSame([], $activity->getLocalImagePaths());
        $this->assertEmpty($this->fileStorage->listContents('', true)->toArray());
    }

    public function testProcessWhenImageDownloadIsSkippedForAnExistingActivity(): void
    {
        $this->settingsRepository->saveGroup(SettingsGroup::IMPORT, ['skipImageDownloadDuringImport' => true]);

        $context = ActivityImportContext::create(
            activityId: ActivityId::fromUnprefixed(1),
            rawStravaData: ['total_photo_count' => 3],
            isNewActivity: false,
        )
            ->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withLocalImagePaths('one', 'two', 'three')
                    ->build()
            );

        $this->assertEquals(
            $context,
            $this->downloadActivityImages->process($context)
        );
        $this->assertEmpty($this->fileStorage->listContents('', true)->toArray());
    }

    public function testProcessWhenClientExceptionIsThrown(): void
    {
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);
        $this->strava->triggerExceptionOnNextCall();

        $context = ActivityImportContext::create(
            activityId: ActivityId::fromUnprefixed(1),
            rawStravaData: ['total_photo_count' => 3],
            isNewActivity: true,
        )
            ->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withLocalImagePaths('one', 'two', 'three')
                    ->build()
            );

        $this->assertEquals(
            $context,
            $this->downloadActivityImages->process($context)
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->downloadActivityImages = new DownloadActivityImages(
            $this->strava = $this->getContainer()->get(Strava::class),
            $this->fileStorage = new Filesystem(new InMemoryFilesystemAdapter()),
            $this->getContainer()->get(UuidFactory::class),
            $this->settingsRepository = $this->getContainer()->get(SettingsRepository::class)
        );
    }
}
