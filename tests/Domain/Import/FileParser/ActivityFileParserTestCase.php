<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser;

use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Lap\ActivityLapRepository;
use App\Domain\Activity\Shifting\ActivityGearUsageRepository;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Import\FileParser\ParsedActivityFile;
use App\Domain\Import\FileParser\RawActivityFile;
use App\Infrastructure\ValueObject\String\Path;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideSnapshotAssertion;
use Spatie\Snapshots\MatchesSnapshots;

abstract class ActivityFileParserTestCase extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideSnapshotAssertion;

    protected function assertParsedFileMatchesSnapshot(ParsedActivityFile $parsed): void
    {
        $activity = $parsed->getActivity();

        $this->getContainer()->get(ActivityRepository::class)->add(
            ActivityWithRawData::fromState($activity, [])
        );
        foreach ($parsed->getStreams() as $stream) {
            $this->getContainer()->get(ActivityStreamRepository::class)->add($stream);
        }
        $this->getContainer()->get(ActivityRepository::class)->markActivityStreamsAsImported($activity->getId());
        foreach ($parsed->getLaps() as $lap) {
            $this->getContainer()->get(ActivityLapRepository::class)->add($lap);
        }
        foreach ($parsed->getGearUsages() as $gearUsage) {
            $this->getContainer()->get(ActivityGearUsageRepository::class)->add($gearUsage);
        }

        $this->assertMatchesJsonSnapshot(
            $this->getConnection()->executeQuery('SELECT * FROM Activity')->fetchAllAssociative()
        );
        $this->assertMatchesJsonSnapshot(
            $this->getConnection()->executeQuery('SELECT * FROM ActivityLap ORDER BY lapNumber ASC')->fetchAllAssociative()
        );
        $this->assertCompressedDatabaseQueryMatchesSnapshot('SELECT * FROM ActivityStream ORDER BY streamType ASC');
        $this->assertMatchesJsonSnapshot(
            $this->getConnection()->executeQuery('SELECT * FROM ActivityGearUsage ORDER BY position, gearNumber')->fetchAllAssociative()
        );
    }

    protected function rawFileFromFixture(string $name): RawActivityFile
    {
        $path = __DIR__.'/fixtures/'.$name;
        $contents = file_get_contents($path);
        if (false === $contents) {
            self::fail(sprintf('Could not read fixture "%s"', $name));
        }

        return RawActivityFile::from(Path::fromString($path), $contents);
    }
}
