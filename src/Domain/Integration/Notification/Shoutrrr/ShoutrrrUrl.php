<?php

namespace App\Domain\Integration\Notification\Shoutrrr;

use App\Infrastructure\ValueObject\String\NonEmptyStringLiteral;
use Uri\Rfc3986\Uri;

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

        return self::fromString(sprintf('%s?%s', $this, http_build_query($params)));
    }

    public function isTelegramUrl(): bool
    {
        return str_starts_with((string) $this, 'telegram://');
    }

    public static function fromDeprecatedNtfyConfig(
        string $ntfyUrl,
        ?string $ntfyUsername,
        ?string $ntfyPassword,
    ): self {
        $uri = new Uri($ntfyUrl);

        if ($ntfyUsername && $ntfyPassword) {
            return self::fromString(sprintf(
                'ntfy://%s:%s@%s',
                rawurlencode($ntfyUsername),
                rawurlencode($ntfyPassword),
                $uri->getHost().$uri->getPath()
            ));
        }

        return self::fromString(sprintf('ntfy://%s', $uri->getHost().$uri->getPath()));
    }
}
