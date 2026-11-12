<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\String;

readonly class Url extends NonEmptyStringLiteral
{
    #[\Override]
    protected function validate(string $value): void
    {
        parent::validate($value);
        if (false === filter_var($value, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException(sprintf('Invalid url "%s"', $value));
        }
    }
}
