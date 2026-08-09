<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Fragment;

use App\Infrastructure\Cache\Cacheability;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class ResolvedFragment implements Fragment
{
    /**
     * @param \Closure(): ?string $render
     */
    public function __construct(
        private string $path,
        private Cacheability $cacheability,
        private \Closure $render,
        private FragmentType $type = FragmentType::PAGE,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getType(): FragmentType
    {
        return $this->type;
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
