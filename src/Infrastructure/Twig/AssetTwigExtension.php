<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Infrastructure\Http\AssetVersioningStrategy;
use Twig\Attribute\AsTwigFunction;

final readonly class AssetTwigExtension
{
    public function __construct(
        private AssetVersioningStrategy $assetVersioningStrategy,
    ) {
    }

    #[AsTwigFunction('assetVersion')]
    public function assetVersion(): string
    {
        return $this->assetVersioningStrategy->getVersion('');
    }
}
