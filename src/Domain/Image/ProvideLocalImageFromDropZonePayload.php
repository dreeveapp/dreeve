<?php

declare(strict_types=1);

namespace App\Domain\Image;

use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\Path;

trait ProvideLocalImageFromDropZonePayload
{
    /**
     * @param array<string, mixed> $payload
     *
     * @return array{0: array<NewImage>, 1: array<RemovedImage>}
     */
    private static function parseImages(array $payload, string $payloadKey): array
    {
        $newImages = [];
        $removedImages = [];
        if (!array_key_exists($payloadKey, $payload)) {
            return [$newImages, $removedImages];
        }

        if (!is_string($payload[$payloadKey])) {
            throw CouldNotDeserializeCommand::invalidPayload(sprintf('The "%s" field is invalid.', $payloadKey));
        }

        $decodedImages = Json::decode($payload[$payloadKey]);
        if (!is_array($decodedImages)) {
            throw CouldNotDeserializeCommand::invalidPayload(sprintf('The "%s" field is invalid.', $payloadKey));
        }

        foreach ($decodedImages as $image) {
            if (!is_array($image) || !isset($image['status']) || !is_string($image['status']) || !$status = ImageStatus::tryFrom($image['status'])) {
                throw CouldNotDeserializeCommand::invalidPayload('Each image requires a valid "status".');
            }

            if (ImageStatus::NEW === $status) {
                if (!isset($image['filename'], $image['content']) || !is_string($image['filename']) || !is_string($image['content'])) {
                    throw CouldNotDeserializeCommand::invalidPayload('A new image requires a "filename" and "content".');
                }

                $filename = Path::fromString(trim($image['filename']));
                if (!ImageExtension::isSupported($filename->getExtension())) {
                    throw CouldNotDeserializeCommand::invalidPayload('Unsupported image file type.');
                }

                $content = base64_decode($image['content'], true);
                if (false === $content || '' === $content) {
                    throw CouldNotDeserializeCommand::invalidPayload('A new image has invalid "content".');
                }

                $newImages[] = new NewImage(
                    filename: $filename,
                    content: $content
                );
            }
            if (ImageStatus::REMOVED === $status) {
                if (!isset($image['path']) || !is_string($image['path']) || '' === trim($image['path'])) {
                    throw CouldNotDeserializeCommand::invalidPayload('A removed image requires a "path".');
                }
                $removedImages[] = new RemovedImage(trim($image['path']));
            }
        }

        return [$newImages, $removedImages];
    }
}
