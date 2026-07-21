<?php

declare(strict_types=1);

namespace App\Domain\Settings\Api;

final class CouldNotResolveConfigResource extends \RuntimeException
{
    public static function withName(string $name): self
    {
        return new self(sprintf('Configuration resource "%s" does not exist.', $name));
    }
}
