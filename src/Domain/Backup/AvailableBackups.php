<?php

declare(strict_types=1);

namespace App\Domain\Backup;

use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Symfony\Component\DependencyInjection\Attribute\Target;

final readonly class AvailableBackups
{
    private const string FOLDER_NAME = 'backups';

    public function __construct(
        #[Target('default.storage')]
        private FilesystemOperator $defaultStorage,
    ) {
    }

    public function findLatest(): ?Backup
    {
        /** @var list<FileAttributes> $backups */
        $backups = $this->defaultStorage->listContents(self::FOLDER_NAME, false)
            ->filter(fn (StorageAttributes $attributes): bool => $attributes instanceof FileAttributes
                && 1 === preg_match('/^dreeve-\d{4}-\d{2}-\d{2}-\d{6}\.tar\.zst$/', basename($attributes->path()))
                && !$this->defaultStorage->directoryExists(substr($attributes->path(), 0, -strlen('.tar.zst'))))
            ->sortByPath()
            ->toArray();

        if (null === $latestBackup = array_last($backups)) {
            return null;
        }

        return Backup::create(
            filename: basename($latestBackup->path()),
            sizeInBytes: (int) $latestBackup->fileSize(),
        );
    }

    public function passThrough(Backup $backup): void
    {
        fpassthru($this->defaultStorage->readStream(self::FOLDER_NAME.'/'.$backup->getFilename()));
    }
}
