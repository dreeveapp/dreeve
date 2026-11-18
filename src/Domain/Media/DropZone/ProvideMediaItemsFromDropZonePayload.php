<?php

declare(strict_types=1);

namespace App\Domain\Media\DropZone;

use App\Domain\Media\ImageExtension;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\NewMediaItem;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\Path;

trait ProvideMediaItemsFromDropZonePayload
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array{0: array<NewMediaItem>, 1: array<RemovedMediaItem>}
     */
    private static function parseMediaItems(array $payload, string $payloadKey): array
    {
        $newMediaItems = [];
        $removedMediaItems = [];
        if (!array_key_exists($payloadKey, $payload)) {
            return [$newMediaItems, $removedMediaItems];
        }

        if (!is_string($payload[$payloadKey])) {
            throw CouldNotDeserializeCommand::invalidPayload(sprintf('The "%s" field is invalid.', $payloadKey));
        }

        $decodedMediaItems = Json::decode($payload[$payloadKey]);
        if (!is_array($decodedMediaItems)) {
            throw CouldNotDeserializeCommand::invalidPayload(sprintf('The "%s" field is invalid.', $payloadKey));
        }

        foreach ($decodedMediaItems as $mediaItem) {
            if (!is_array($mediaItem) || !isset($mediaItem['status']) || !is_string($mediaItem['status']) || !$status = DropZoneItemStatus::tryFrom($mediaItem['status'])) {
                throw CouldNotDeserializeCommand::invalidPayload('Each image requires a valid "status".');
            }

            if (DropZoneItemStatus::NEW === $status) {
                if (!isset($mediaItem['filename'], $mediaItem['content']) || !is_string($mediaItem['filename']) || !is_string($mediaItem['content'])) {
                    throw CouldNotDeserializeCommand::invalidPayload('A new image requires a "filename" and "content".');
                }

                $filename = Path::fromString(trim($mediaItem['filename']));
                if (!ImageExtension::isSupported($filename->getExtension())) {
                    throw CouldNotDeserializeCommand::invalidPayload('Unsupported image file type.');
                }

                $content = base64_decode($mediaItem['content'], true);
                if (false === $content || '' === $content) {
                    throw CouldNotDeserializeCommand::invalidPayload('A new image has invalid "content".');
                }

                $newMediaItems[] = new NewMediaItem(
                    filename: $filename,
                    content: $content,
                );
            }
            if (DropZoneItemStatus::REMOVED === $status) {
                if (!isset($mediaItem['id']) || !is_string($mediaItem['id']) || '' === trim($mediaItem['id'])) {
                    throw CouldNotDeserializeCommand::invalidPayload('A removed image requires an "id".');
                }
                try {
                    $removedMediaItems[] = new RemovedMediaItem(MediaItemId::fromString(trim($mediaItem['id'])));
                } catch (\InvalidArgumentException) {
                    throw CouldNotDeserializeCommand::invalidPayload('A removed image has an invalid "id".');
                }
            }
        }

        return [$newMediaItems, $removedMediaItems];
    }
}
