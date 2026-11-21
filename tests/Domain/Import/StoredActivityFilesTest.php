<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ImportSource;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Domain\Import\FileImportStatus;
use App\Domain\Import\FileParser\ActivityFileParsers;
use App\Domain\Import\StoredActivityFiles;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class StoredActivityFilesTest extends ContainerTestCase
{
    public function testParseYieldsEveryParsableStoredFileAndReportsTheRest(): void
    {
        $fileImportRepository = $this->getContainer()->get(FileImportRepository::class);
        $imports = [
            ['1', 'morning-run.fit', (string) file_get_contents(__DIR__.'/FileParser/fixtures/activity.fit'), FileImportStatus::SUCCESS, 'fit'],
            ['2', 'evening-ride.tcx', (string) file_get_contents(__DIR__.'/FileParser/fixtures/activity.tcx'), FileImportStatus::SUCCESS, 'tcx'],
            ['3', 'lunch-walk.gpx', (string) file_get_contents(__DIR__.'/FileParser/fixtures/activity.gpx'), FileImportStatus::SUCCESS, 'gpx'],
            ['4', 'broken.fit', 'not a fit file', FileImportStatus::SUCCESS, 'broken'],
            ['5', 'notes.txt', 'not an activity', FileImportStatus::SUCCESS, 'unsupported'],
            ['6', 'skipped.gpx', (string) file_get_contents(__DIR__.'/FileParser/fixtures/activity.gpx'), FileImportStatus::SKIPPED, 'skipped'],
            ['7', 'no-contents.gpx', null, FileImportStatus::SUCCESS, 'no-contents'],
            ['8', 'no-activity.gpx', (string) file_get_contents(__DIR__.'/FileParser/fixtures/activity.gpx'), FileImportStatus::SUCCESS, null],
            ['9', 'some/dir/evening-hike.gpx', (string) file_get_contents(__DIR__.'/FileParser/fixtures/activity.gpx'), FileImportStatus::SUCCESS, 'nested'],
        ];
        foreach ($imports as $i => [$fileImportId, $originalFilename, $fileContents, $status, $activityId]) {
            $fileImportRepository->add(
                FileImportBuilder::fromDefaults()
                    ->withFileImportId(FileImportId::fromUnprefixed($fileImportId))
                    ->withOriginalFilename($originalFilename)
                    ->withFileContents($fileContents)
                    ->withSource(ImportSource::FIT_FILE)
                    ->withStatus($status)
                    ->withActivityId(ActivityId::fromOptionalUnprefixed($activityId))
                    ->withImportedOn(SerializableDateTime::fromString(sprintf('2026-06-04 10:%02d:00', $i)))
                    ->build()
            );
        }

        $this->getConnection()->executeStatement(
            'INSERT INTO FileImport (fileImportId, originalFilename, fileContents, source, status, errorMessage, activityId, importedOn)
            VALUES (:fileImportId, :originalFilename, :fileContents, :source, :status, NULL, :activityId, :importedOn)',
            [
                'fileImportId' => (string) FileImportId::fromUnprefixed('corrupt'),
                'originalFilename' => 'corrupt.gpx',
                'fileContents' => 'not zstd',
                'source' => ImportSource::GPX_FILE->value,
                'status' => FileImportStatus::SUCCESS->value,
                'activityId' => (string) ActivityId::fromUnprefixed('corrupt'),
                'importedOn' => '2026-06-04 10:20:00',
            ],
        );

        $failures = [];
        $externalReferenceIds = [];
        foreach (new StoredActivityFiles(
            fileImportRepository: $fileImportRepository,
            activityFileParsers: $this->getContainer()->get(ActivityFileParsers::class),
        )->parse(function (string $failure) use (&$failures): void {
            $failures[] = $failure;
        }) as $activityId => $parsedActivityFile) {
            $externalReferenceIds[(string) $activityId] = (string) $parsedActivityFile->getActivity()->getExternalReferenceId();
        }

        $this->assertSame(
            [
                'activity-fit' => 'morning-run.fit',
                'activity-tcx' => 'evening-ride.tcx',
                'activity-gpx' => 'lunch-walk.gpx',
                'activity-nested' => 'evening-hike.gpx',
            ],
            $externalReferenceIds,
        );
        $this->assertCount(3, $failures);
        $this->assertStringStartsWith('activity-broken (broken.fit): ', $failures[0]);
        $this->assertStringStartsWith('activity-unsupported (notes.txt): No parser available', $failures[1]);
        $this->assertStringStartsWith(sprintf('%s: ZSTD decompression failed', FileImportId::fromUnprefixed('corrupt')), $failures[2]);
    }
}
