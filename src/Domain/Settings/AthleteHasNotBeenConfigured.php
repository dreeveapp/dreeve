<?php

declare(strict_types=1);

namespace App\Domain\Settings;

final class AthleteHasNotBeenConfigured extends \RuntimeException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
