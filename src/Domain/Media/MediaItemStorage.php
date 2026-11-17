<?php

declare(strict_types=1);

namespace App\Domain\Media;

use App\Domain\Media\Metadata\MediaItemMetadataReader;
use App\Infrastructure\ValueObject\Identifier\UuidFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Target;

final readonly class MediaItemStorage
{
    public function __construct(
        #[Target('file.storage')]
        private FilesystemOperator $fileStorage,
        private MediaItemRepository $mediaItemRepository,
        private MediaItemMetadataReader $mediaItemMetadataReader,
        private UuidFactory $uuidFactory,
    ) {
    }

    public function store(NewMediaItem $newMediaItem, MediaItemDirectory $directory): MediaItem
    {
        return $this->storeWithId(
            mediaItemId: MediaItemId::fromUnprefixed($this->uuidFactory->random()),
            newMediaItem: $newMediaItem,
            directory: $directory,
        );
    }

    public function storeWithId(MediaItemId $mediaItemId, NewMediaItem $newMediaItem, MediaItemDirectory $directory): MediaItem
    {
        $path = MediaItemPath::fromFileSystemPath(sprintf(
            '%s/%s.%s',
            $directory->value,
            $mediaItemId->toUnprefixedString(),
            $newMediaItem->getFilename()->getExtension(),
        ));
        $this->fileStorage->write($path->toFileSystemPath(), $newMediaItem->getContent());

        $metadata = $this->mediaItemMetadataReader->read($newMediaItem->getContent());
        $mediaItem = MediaItem::create(
            mediaItemId: $mediaItemId,
            path: $path,
            width: $metadata->getWidth(),
            height: $metadata->getHeight(),
            coordinate: $metadata->getCoordinate(),
            takenOn: $metadata->getTakenOn(),
        );
        $this->mediaItemRepository->add($mediaItem);

        return $mediaItem;
    }

    public function remove(MediaItemId $mediaItemId): void
    {
        $mediaItem = $this->mediaItemRepository->find($mediaItemId);
        $fileSystemPath = $mediaItem->getPath()->toFileSystemPath();
        if ($this->fileStorage->fileExists($fileSystemPath)) {
            $this->fileStorage->delete($fileSystemPath);
        }
        $this->mediaItemRepository->delete($mediaItem);
    }
}
