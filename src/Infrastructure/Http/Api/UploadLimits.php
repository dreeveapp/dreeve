<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

final readonly class UploadLimits
{
    private function __construct(
        private int $maxPostSizeInBytes,
    ) {
    }

    public static function fromIni(): self
    {
        return self::fromShorthandValue((string) ini_get('post_max_size'));
    }

    public static function fromShorthandValue(string $postMaxSize): self
    {
        return new self(self::toBytes($postMaxSize));
    }

    public function getMaxPostSizeInBytes(): int
    {
        return $this->maxPostSizeInBytes;
    }

    private static function toBytes(string $shorthand): int
    {
        $shorthand = trim($shorthand);
        if ('' === $shorthand) {
            return 0;
        }

        $value = (int) $shorthand;

        return match (strtolower(substr($shorthand, -1))) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
