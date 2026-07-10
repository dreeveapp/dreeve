<?php

declare(strict_types=1);

namespace App\Infrastructure\ValueObject\String;

readonly class Slug extends NonEmptyStringLiteral
{
    public function __toString(): string
    {
        return $this->kebabCase();
    }
}
