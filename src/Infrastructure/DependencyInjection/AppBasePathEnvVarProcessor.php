<?php

declare(strict_types=1);

namespace App\Infrastructure\DependencyInjection;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * Turns the URL in an env var into the path the app is served from: "%env(app_base_path:APP_URL)%".
 * Always has a leading slash and never a trailing one, except for the root which is "/".
 * Used to scope cookies to the sub-path Dreeve is hosted on.
 */
final readonly class AppBasePathEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $path = parse_url((string) $getEnv($name), PHP_URL_PATH);
        $trimmed = trim(is_string($path) ? $path : '', '/');

        return '' === $trimmed ? '/' : '/'.$trimmed;
    }

    public static function getProvidedTypes(): array
    {
        return [
            'app_base_path' => 'string',
        ];
    }
}
