<?php

declare(strict_types=1);

namespace App\Domain\Image;

use App\Infrastructure\ValueObject\Identifier\UuidFactory;
use League\Flysystem\FilesystemOperator;

final readonly class ImageStorage
{
    public function __construct(
        private FilesystemOperator $fileStorage,
        private UuidFactory $uuidFactory,
    ) {
    }

    public function store(NewImage $newImage, ImageDirectory $directory): ImagePath
    {
        $fileSystemPath = sprintf(
            '%s/%s.%s',
            $directory->value,
            $this->uuidFactory->random(),
            $newImage->getFilename()->getExtension(),
        );
        $path = ImagePath::fromFileSystemPath($fileSystemPath);
        $this->storeAt($path, $newImage->getContent());

        return $path;
    }

    public function storeAt(ImagePath $path, string $content): void
    {
        $this->fileStorage->write($path->toFileSystemPath(), $content);
    }

    public function remove(ImagePath $path): void
    {
        $fileSystemPath = $path->toFileSystemPath();
        if ($this->fileStorage->fileExists($fileSystemPath)) {
            $this->fileStorage->delete($fileSystemPath);
        }
    }
}
