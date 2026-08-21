<?php

declare(strict_types=1);

namespace App\Domain\Import;

final class InvalidActivityFileName extends \InvalidArgumentException
{
    public static function isNotValid(): self
    {
        return new self('The file name is not valid.');
    }

    public static function isTooLong(int $maxLengthInBytes): self
    {
        return new self(sprintf('The file name cannot be longer than %d bytes.', $maxLengthInBytes));
    }

    public static function fileTypeIsNotSupported(): self
    {
        return new self('The file type is not supported.');
    }
}
