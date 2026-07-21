<?php

declare(strict_types=1);

namespace App\Application\Import\FileImport\ImportActivityFiles\Pipeline;

use App\Domain\Activity\Activity;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\Lap\ActivityLaps;
use App\Domain\Activity\Stream\ActivityStreams;
use App\Domain\Import\FileParser\RawActivityFile;
use App\Infrastructure\ValueObject\String\Path;

final readonly class ActivityImportContext
{
    private function __construct(
        private Path $filePath,
        private ?RawActivityFile $file,
        private ?Activity $activity,
        private ActivityStreams $streams,
        private ActivityLaps $laps,
    ) {
    }

    public static function create(
        Path $filePath,
    ): self {
        return new self(
            filePath: $filePath,
            file: null,
            activity: null,
            streams: ActivityStreams::empty(),
            laps: ActivityLaps::empty(),
        );
    }

    public function getImportSource(): ImportSource
    {
        return match ($this->filePath->getExtension()) {
            'fit' => ImportSource::FIT_FILE,
            'tcx' => ImportSource::TCX_FILE,
            'gpx' => ImportSource::GPX_FILE,
            default => throw new \RuntimeException(sprintf('Unknown file extension "%s"', $this->filePath->getExtension())),
        };
    }

    public function withActivity(Activity $activity): self
    {
        return clone ($this, [
            'activity' => $activity,
        ]);
    }

    public function withStreams(ActivityStreams $streams): self
    {
        return clone ($this, [
            'streams' => $streams,
        ]);
    }

    public function withLaps(ActivityLaps $laps): self
    {
        return clone ($this, [
            'laps' => $laps,
        ]);
    }

    public function withFile(RawActivityFile $file): self
    {
        return clone ($this, [
            'file' => $file,
        ]);
    }

    public function getFile(): ?RawActivityFile
    {
        return $this->file;
    }

    public function getFilePath(): Path
    {
        return $this->filePath;
    }

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function getStreams(): ActivityStreams
    {
        return $this->streams;
    }

    public function getLaps(): ActivityLaps
    {
        return $this->laps;
    }
}
