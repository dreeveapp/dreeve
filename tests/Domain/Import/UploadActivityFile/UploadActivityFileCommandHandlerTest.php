<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\UploadActivityFile;

use App\Domain\Import\ImportMode;
use App\Domain\Import\UploadActivityFile\CannotUploadActivityFile;
use App\Domain\Import\UploadActivityFile\UploadActivityFile;
use App\Domain\Import\UploadActivityFile\UploadActivityFileCommandHandler;
use App\Domain\Import\WatchDirectory;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Infrastructure\ValueObject\String\Path;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;

class UploadActivityFileCommandHandlerTest extends TestCase
{
    private Filesystem $filesystem;
    private WatchDirectory $watchDirectory;

    public function testHandleWritesFileInFilesMode(): void
    {
        $handler = new UploadActivityFileCommandHandler(ImportMode::FILES, $this->watchDirectory);

        $handler->handle(UploadActivityFile::fromPayload([
            'filename' => 'ride.fit',
            'content' => base64_encode('raw-fit-bytes'),
        ]));

        $this->assertSame('raw-fit-bytes', $this->watchDirectory->readFile(Path::fromString('ride.fit')));
    }

    public function testHandleThrowsInStravaApiModeAndWritesNothing(): void
    {
        $handler = new UploadActivityFileCommandHandler(ImportMode::STRAVA_API, $this->watchDirectory);

        $command = UploadActivityFile::fromPayload([
            'filename' => 'ride.fit',
            'content' => base64_encode('raw-fit-bytes'),
        ]);

        $this->expectExceptionObject(new CannotUploadActivityFile('Activity files can only be uploaded when running in file import mode.'));

        try {
            $handler->handle($command);
        } finally {
            $this->assertFalse($this->filesystem->fileExists('watch/ride.fit'));
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->watchDirectory = new WatchDirectory(
            KernelProjectDir::fromString('/project/dir'),
            $this->filesystem,
        );
    }
}
