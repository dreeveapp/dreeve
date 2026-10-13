<?php

declare(strict_types=1);

namespace App\Domain\Gear\RecordingDevice;

use App\Infrastructure\ValueObject\Identifier\Identifier;
use App\Infrastructure\ValueObject\String\Name;

final readonly class RecordingDeviceId extends Identifier
{
    public static function getPrefix(): string
    {
        return 'recordingDevice-';
    }

    public static function none(): self
    {
        return self::fromUnprefixed('none');
    }

    public static function fromName(string $name): self
    {
        return self::fromUnprefixed(Name::fromString($name)->kebabCase());
    }
}
