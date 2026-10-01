<?php

namespace App\Domain\Integration\Notification\Shoutrrr;

use App\Infrastructure\ValueObject\String\NonEmptyStringLiteral;

final readonly class ShoutrrrUrl extends NonEmptyStringLiteral
{
    /**
     * @param array<string, string|int|float|bool> $params
     */
    public function withParams(array $params): self
    {
        if ([] === $params) {
            return $this;
        }

        return self::fromString(sprintf(
            '%s%s%s',
            $this,
            str_contains((string) $this, '?') ? '&' : '?',
            http_build_query($params)
        ));
    }

    public function isNtfyUrl(): bool
    {
        return str_starts_with((string) $this, 'ntfy://');
    }
}
