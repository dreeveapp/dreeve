<?php

declare(strict_types=1);

namespace App\Domain\Import;

use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Infrastructure\ValueObject\String\Path;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;

final readonly class WatchDirectory
{
    private const string FOLDER_NAME = 'watch';

    public function __construct(
        private KernelProjectDir $projectDir,
        private FilesystemOperator $defaultStorage,
    ) {
    }

    public function exists(): bool
    {
        return $this->defaultStorage->directoryExists(self::FOLDER_NAME);
    }

    public function hasFilesThatCanBeProcessed(): bool
    {
        return [] !== $this->listFiles()
            ->filter(fn (StorageAttributes $file): bool => in_array(
                Path::fromString($file->path())->getExtension(),
                array_map(fn (SupportedFileExtension $ext) => $ext->value, SupportedFileExtension::cases()),
            ))
            ->toArray();
    }

    public function listFiles(): DirectoryListing
    {
        return $this->defaultStorage->listContents(self::FOLDER_NAME, false)
            ->filter(fn (StorageAttributes $attributes): bool => $attributes->isFile());
    }

    public function readFile(Path $filePath): string
    {
        return $this->defaultStorage->read(self::FOLDER_NAME.'/'.$filePath->getBasename());
    }

    public function writeFile(string $filename, string $contents): void
    {
        $this->defaultStorage->write(self::FOLDER_NAME.'/'.$filename, $contents);
    }

    public function deleteFile(Path $filePath): void
    {
        $this->defaultStorage->delete(self::FOLDER_NAME.'/'.$filePath->getBasename());
    }

    public function getAbsolutePathFor(StorageAttributes $file): Path
    {
        return Path::fromString($this->projectDir.'/'.$file->path());
    }
}
