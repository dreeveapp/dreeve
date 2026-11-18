<?php

namespace App\Tests\Application\Import\StravaImport\ImportActivities\Pipeline;

use App\Application\Import\StravaImport\ImportActivities\Pipeline\ActivityImportContext;
use App\Application\Import\StravaImport\ImportActivities\Pipeline\DownloadActivityImages;
use App\Domain\Activity\ActivityId;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemIds;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Domain\Media\MediaItems;
use App\Domain\Media\MediaItemStorage;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Strava\Strava;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Media\MediaItemBuilder;
use App\Tests\Domain\Strava\SpyStrava;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use League\Flysystem\FilesystemOperator;

class DownloadActivityImagesTest extends ContainerTestCase
{
    private DownloadActivityImages $downloadActivityImages;
    private SpyStrava $strava;
    private FilesystemOperator $fileStorage;
    private SettingsRepository $settingsRepository;

    public function testProcess(): void
    {
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);

        $context = ActivityImportContext::create(
            activityId: ActivityId::fromUnprefixed('1'),
            rawStravaData: ['total_photo_count' => 2],
            isNewActivity: true,
        )
            ->withActivity(
                ActivityBuilder::fromDefaults()->build()
            );

        $activity = $this->downloadActivityImages->process($context)->getActivity();
        $this->assertNotNull($activity);

        $this->assertEquals(MediaItemIds::fromArray([MediaItemId::fromUnprefixed('0025176c-5652-11ee-923d-02424dd627d5')]), $activity->getMediaItemIds());
        $this->assertSame('/tests/cat.webp', $this->fileStorage->read('activities/0025176c-5652-11ee-923d-02424dd627d5.png'));
        $this->assertEquals(
            MediaItems::fromArray([
                MediaItemBuilder::fromDefaults()
                    ->withMediaItemId(MediaItemId::fromUnprefixed('0025176c-5652-11ee-923d-02424dd627d5'))
                    ->withPath(MediaItemPath::fromString('activities/0025176c-5652-11ee-923d-02424dd627d5.png'))
                    ->withCoordinate(Coordinate::createFromLatAndLng(
                        latitude: Latitude::fromString('51.2'),
                        longitude: Longitude::fromString('3.18'),
                    ))
                    ->withTakenOn(SerializableDateTime::fromString('2023-09-11 21:20:12'))
                    ->build(),
            ]),
            $this->getContainer()->get(MediaItemRepository::class)->findByIds(MediaItemIds::fromArray([
                MediaItemId::fromUnprefixed('0025176c-5652-11ee-923d-02424dd627d5'),
            ])),
        );
    }

    public function testProcessWhenImagesShouldNotBeDownloaded(): void
    {
        $context = ActivityImportContext::create(
            activityId: ActivityId::fromUnprefixed('1'),
            rawStravaData: ['total_photo_count' => 3],
            isNewActivity: false,
        )
            ->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withMediaItemIds(MediaItemId::fromUnprefixed('one'), MediaItemId::fromUnprefixed('two'), MediaItemId::fromUnprefixed('three'))
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
            activityId: ActivityId::fromUnprefixed('1'),
            rawStravaData: ['total_photo_count' => 3],
            isNewActivity: true,
        )
            ->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withTotalImageCount(3)
                    ->build()
            );

        $activity = $this->downloadActivityImages->process($context)->getActivity();
        $this->assertNotNull($activity);

        $this->assertSame(0, $activity->getTotalImageCount());
        $this->assertTrue($activity->getMediaItemIds()->isEmpty());
        $this->assertEmpty($this->fileStorage->listContents('', true)->toArray());
    }

    public function testProcessWhenImageDownloadIsSkippedForAnExistingActivity(): void
    {
        $this->settingsRepository->saveGroup(SettingsGroup::IMPORT, ['skipImageDownloadDuringImport' => true]);

        $context = ActivityImportContext::create(
            activityId: ActivityId::fromUnprefixed('1'),
            rawStravaData: ['total_photo_count' => 3],
            isNewActivity: false,
        )
            ->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withMediaItemIds(MediaItemId::fromUnprefixed('one'), MediaItemId::fromUnprefixed('two'), MediaItemId::fromUnprefixed('three'))
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
            activityId: ActivityId::fromUnprefixed('1'),
            rawStravaData: ['total_photo_count' => 3],
            isNewActivity: true,
        )
            ->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withMediaItemIds(MediaItemId::fromUnprefixed('one'), MediaItemId::fromUnprefixed('two'), MediaItemId::fromUnprefixed('three'))
                    ->build()
            );

        $this->assertEquals(
            $context,
            $this->downloadActivityImages->process($context)
        );
    }

    public function testProcessRemovesStoredMediaItemsWhenTheDownloadFailsPartWay(): void
    {
        $this->strava->setMaxNumberOfCallsBeforeTriggering429(1000);

        $mediaItemRepository = $this->createMock(MediaItemRepository::class);
        $mediaItemRepository
            ->expects($this->once())
            ->method('update')
            ->willThrowException(new RequestException('The error', new Request('GET', 'uri')));

        $downloadActivityImages = new DownloadActivityImages(
            strava: $this->strava,
            mediaItemStorage: $this->getContainer()->get(MediaItemStorage::class),
            mediaItemRepository: $mediaItemRepository,
            settingsRepository: $this->settingsRepository,
        );

        $context = ActivityImportContext::create(
            activityId: ActivityId::fromUnprefixed('1'),
            rawStravaData: ['total_photo_count' => 2],
            isNewActivity: false,
        )
            ->withActivity(
                ActivityBuilder::fromDefaults()
                    ->withMediaItemIds(MediaItemId::fromUnprefixed('one'))
                    ->build()
            );

        $this->assertEquals(
            $context,
            $downloadActivityImages->process($context)
        );
        $this->assertFalse($this->fileStorage->fileExists('activities/0025176c-5652-11ee-923d-02424dd627d5.png'));
        $this->assertTrue($this->getContainer()->get(MediaItemRepository::class)->findByIds(MediaItemIds::fromArray([
            MediaItemId::fromUnprefixed('0025176c-5652-11ee-923d-02424dd627d5'),
        ]))->isEmpty());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->downloadActivityImages = new DownloadActivityImages(
            strava: $this->strava = $this->getContainer()->get(Strava::class),
            mediaItemStorage: $this->getContainer()->get(MediaItemStorage::class),
            mediaItemRepository: $this->getContainer()->get(MediaItemRepository::class),
            settingsRepository: $this->settingsRepository = $this->getContainer()->get(SettingsRepository::class),
        );
        $this->fileStorage = $this->getContainer()->get('file.storage');
    }
}
