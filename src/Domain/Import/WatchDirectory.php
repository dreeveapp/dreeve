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
    private const string STAGING_FOLDER_NAME = self::FOLDER_NAME.'/.uploads';

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
        $processableFiles = $this->listFiles()
            ->filter(fn (StorageAttributes $file): bool => in_array(
                Path::fromString($file->path())->getExtension(),
                array_map(fn (SupportedFileExtension $ext) => $ext->value, SupportedFileExtension::cases()),
            ));

        foreach ($processableFiles as $processableFile) {
            return true;
        }

        return false;
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

    public function writeFile(ActivityFileName $filename, string $contents): ActivityFileName
    {
        $availableFilename = $filename;
        $suffix = 1;
        while ($this->defaultStorage->fileExists(self::FOLDER_NAME.'/'.$availableFilename)) {
            $availableFilename = $filename->withSuffix((string) $suffix++);
        }

        $stagedPath = self::STAGING_FOLDER_NAME.'/'.$availableFilename;
        $this->defaultStorage->write($stagedPath, $contents);
        $this->defaultStorage->move($stagedPath, self::FOLDER_NAME.'/'.$availableFilename);

        return $availableFilename;
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
