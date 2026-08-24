<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Request;

use App\Application\AppUrl;
use Symfony\Component\HttpFoundation\Request;
use Uri\WhatWg\Url;

final readonly class RedirectTo
{
    public const string QUERY_PARAM = 'redirectTo';

    private function __construct(
        private string $url,
    ) {
    }

    public static function fromRequest(Request $request, AppUrl $appUrl): ?self
    {
        $redirectTo = $request->query->all()[self::QUERY_PARAM] ?? null;
        if (!is_string($redirectTo) || '' === $redirectTo = trim($redirectTo)) {
            return null;
        }

        $base = Url::parse((string) $appUrl);
        if (!$base instanceof Url || !($resolved = Url::parse($redirectTo, $base)) instanceof Url) {
            return null;
        }

        if ($resolved->getScheme() !== $base->getScheme()
            || $resolved->getAsciiHost() !== $base->getAsciiHost()
            || $resolved->getPort() !== $base->getPort()) {
            return null;
        }

        $basePath = rtrim($base->getPath(), '/');
        if ($resolved->getPath() !== $basePath && !str_starts_with($resolved->getPath(), $basePath.'/')) {
            return null;
        }

        return new self($resolved->getPath()
            .(is_string($query = $resolved->getQuery()) ? '?'.$query : '')
            .(is_string($fragment = $resolved->getFragment()) ? '#'.$fragment : ''));
    }

    public function __toString(): string
    {
        return $this->url;
    }
}
