<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\RefreshFromFiles;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Domain\Import\RefreshFromFiles\ActivityFieldsToRefresh;
use App\Domain\Import\RefreshFromFiles\RefreshActivitiesFromFiles;
use App\Domain\Import\RefreshFromFiles\RefreshActivitiesFromFilesCommandHandler;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Import\FileImportBuilder;
use App\Tests\SpyOutput;

class RefreshActivitiesFromFilesCommandHandlerTest extends ContainerTestCase
{
    public function testHandle(): void
    {
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        foreach (['fit', 'broken', 'gpx', 'untouched'] as $activityId) {
            $activityRepository->add(ActivityWithRawData::fromState(
                activity: ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withDescent(null)
                    ->build(),
                rawData: ['raw' => $activityId],
            ));
        }

        $fileImportRepository = $this->getContainer()->get(FileImportRepository::class);
        $imports = [
            ['1', 'morning-run.fit', (string) file_get_contents(__DIR__.'/../FileParser/fixtures/activity.fit'), 'fit'],
            ['2', 'broken.fit', 'not a fit file', 'broken'],
            ['3', 'lake-loop.gpx', (string) file_get_contents(__DIR__.'/../FileParser/fixtures/activity-metadata-name-and-description.gpx'), 'gpx'],
            ['4', 'deleted.gpx', (string) file_get_contents(__DIR__.'/../FileParser/fixtures/activity.gpx'), 'deleted'],
        ];
        foreach ($imports as $i => [$fileImportId, $originalFilename, $fileContents, $activityId]) {
            $fileImportRepository->add(
                FileImportBuilder::fromDefaults()
                    ->withFileImportId(FileImportId::fromUnprefixed($fileImportId))
                    ->withOriginalFilename($originalFilename)
                    ->withFileContents($fileContents)
                    ->withActivityId(ActivityId::fromUnprefixed($activityId))
                    ->withImportedOn(SerializableDateTime::fromString(sprintf('2026-06-04 10:%02d:00', $i)))
                    ->build()
            );
        }

        $this->getContainer()->get(KeyValueStore::class)->save(KeyValue::fromState(
            key: Key::ACTIVITY_FILE_REFRESH,
            value: Value::fromString('["descent", "droppedField"]'),
        ));

        $output = new SpyOutput();
        $this->getContainer()->get(RefreshActivitiesFromFilesCommandHandler::class)->handle(new RefreshActivitiesFromFiles($output));

        $this->assertStringStartsWith(
            implode("\n", [
                'Refreshing activities from stored files...',
                '  => <error>Could not refresh activity-broken (broken.fit): ',
            ]),
            (string) $output,
        );
        $this->assertStringEndsWith(
            "</error>\n  => Refreshed 2 activities from stored files",
            (string) $output,
        );

        $fit = $activityRepository->findWithRawData(ActivityId::fromUnprefixed('fit'));
        $this->assertSame(0, $fit->getActivity()->getDescent()?->toInt());
        $this->assertSame(['raw' => 'fit'], $fit->getRawData());
        $this->assertSame(10, $activityRepository->find(ActivityId::fromUnprefixed('gpx'))->getDescent()?->toInt());
        $this->assertNull($activityRepository->find(ActivityId::fromUnprefixed('broken'))->getDescent());
        $this->assertNull($activityRepository->find(ActivityId::fromUnprefixed('untouched'))->getDescent());

        $this->assertFalse($this->getContainer()->get(ActivityFieldsToRefresh::class)->hasAny());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getConnection()->executeStatement(
            'INSERT INTO KeyValue (`key`, `value`) VALUES (:key, :value)',
            ['key' => 'lock.importData', 'value' => '{"lockAcquiredBy": "test"}']
        );
    }
}
