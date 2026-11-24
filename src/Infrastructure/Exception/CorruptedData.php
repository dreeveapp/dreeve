<?php

declare(strict_types=1);

namespace App\Infrastructure\Exception;

final class CorruptedData extends \Exception
{
    public static function invalidJson(\JsonException $previous): self
    {
        return new self(
            message: sprintf('Invalid JSON detected (%s). This is usually caused by corrupted activity data.
Please see the troubleshooting guide for steps to resolve the issue: https://docs.dreeve.app/troubleshooting/import-build-fails/ for more information.', $previous->getMessage()),
            previous: $previous,
        );
    }
}
