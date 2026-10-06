<?php

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\ValueObject\String\Url;

final readonly class AppUrl extends Url
{
    public function getBasePath(): ?string
    {
        if (!$basePath = parse_url((string) $this, PHP_URL_PATH)) {
            return null;
        }

        return ltrim($basePath, '/') ?: null;
    }

    public function withPath(string $path): Url
    {
        return Url::fromString(rtrim((string) $this, '/').'/'.ltrim($path, '/'));
    }
}
