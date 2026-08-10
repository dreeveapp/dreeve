<?php

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\FileSystem\PermissionChecker;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToWriteFile;

final readonly class AppStatusChecker
{
    public function __construct(
        private PermissionChecker $fileSystemPermissionChecker,
    ) {
    }

    public function ensureIsReadyForStravaImport(): void
    {
        $this->ensureFileSystemIsWritable();
    }

    public function ensureIsReadyForFileImport(): void
    {
        $this->ensureFileSystemIsWritable();
    }

    private function ensureFileSystemIsWritable(): void
    {
        try {
            $this->fileSystemPermissionChecker->ensureWriteAccess();
        } catch (UnableToWriteFile|UnableToCreateDirectory) {
            throw AppIsNotReady::becauseFileSystemIsNotWritable();
        }
    }
}
