<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Page;

use App\Infrastructure\Cache\Cacheability;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class ResolvedPage implements Page
{
    /**
     * @param \Closure(): ?string $render
     */
    public function __construct(
        private string $path,
        private Cacheability $cacheability,
        private \Closure $render,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getCacheability(): Cacheability
    {
        return $this->cacheability;
    }

    public function render(): ?string
    {
        return ($this->render)();
    }
}
