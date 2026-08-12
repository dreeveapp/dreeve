<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\AppVersion;
use App\Infrastructure\Config\DevMode;
use App\Infrastructure\ValueObject\Identifier\UuidFactory;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;

final readonly class AssetVersioningStrategy implements VersionStrategyInterface
{
    private const string FORMAT = '%s?%s';

    public function __construct(
        private DevMode $devMode,
        private UuidFactory $uuidFactory,
    ) {
    }

    public function getVersion(string $path): string
    {
        if (!$this->devMode->isEnabled()) {
            return AppVersion::getSemanticVersion();
        }

        return $this->uuidFactory->random();
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
