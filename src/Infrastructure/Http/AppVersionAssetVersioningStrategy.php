<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\AppVersion;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

final readonly class AppVersionAssetVersioningStrategy implements VersionStrategyInterface
{
    private const string FORMAT = '%s?%s';

    public function getVersion(string $path): string
    {
        return AppVersion::getSemanticVersion();
    }

    public function applyVersion(string $path): string
    {
        $versionized = \sprintf(self::FORMAT, ltrim($path, '/'), $this->getVersion($path));

        if ($path && '/' === $path[0]) {
            return '/'.$versionized;
        }

        return $versionized;
    }
}
