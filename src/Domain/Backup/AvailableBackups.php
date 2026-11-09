<?php

declare(strict_types=1);

namespace App\Domain\Backup;

use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Target;

final readonly class AvailableBackups
{
    private const string FOLDER_NAME = 'backups';

    public function __construct(
        #[Target('default.storage')]
        private FilesystemOperator $defaultStorage,
        private KernelProjectDir $kernelProjectDir,
    ) {
    }

    /**
     * @return list<Backup>
     */
    public function findAll(): array
    {
        $backups = [];
        foreach ($this->defaultStorage->listContents(self::FOLDER_NAME, false)->sortByPath() as $attributes) {
            if (!$attributes instanceof FileAttributes
                || 1 !== preg_match('/^dreeve-\d{4}-\d{2}-\d{2}-\d{6}\.tar\.zst$/', basename($attributes->path()))) {
                continue;
            }

            $backups[] = Backup::create(
                filename: basename($attributes->path()),
                sizeInBytes: (int) $attributes->fileSize(),
            );
        }

        return $backups;
    }

    public function findByName(string $filename): Backup
    {
        return array_find(
            $this->findAll(),
            static fn (Backup $backup): bool => $backup->getFilename() === $filename,
        ) ?? throw new EntityNotFound(sprintf('Backup "%s" not found', $filename));
    }

    public function isOnMountedVolume(): bool
    {
        $mounts = is_readable('/proc/self/mountinfo') ? (file('/proc/self/mountinfo', FILE_IGNORE_NEW_LINES) ?: []) : [];

        return array_any($mounts, fn (string $mount): bool => (explode(' ', $mount)[4] ?? null) === $this->kernelProjectDir.'/'.self::FOLDER_NAME);
    }

    public function passThrough(Backup $backup): void
    {
        fpassthru($this->defaultStorage->readStream(self::FOLDER_NAME.'/'.$backup->getFilename()));
    }

    public function delete(Backup $backup): void
    {
        $this->defaultStorage->delete(self::FOLDER_NAME.'/'.$backup->getFilename());
    }
}
