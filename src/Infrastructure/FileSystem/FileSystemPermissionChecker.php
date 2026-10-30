<?php

declare(strict_types=1);

namespace App\Infrastructure\FileSystem;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Target;

final readonly class FileSystemPermissionChecker implements PermissionChecker
{
    public function __construct(
        #[Target('file.storage')]
        private FilesystemOperator $fileStorage,
        #[Target('database.storage')]
        private FilesystemOperator $databaseStorage,
    ) {
    }

    public function ensureWriteAccess(): void
    {
        $this->databaseStorage->write('test.txt', 'success');
        $this->databaseStorage->delete('test.txt');

        $this->fileStorage->write('test.txt', 'success');
        $this->fileStorage->delete('test.txt');
    }
}
