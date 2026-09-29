<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Fragment;

use App\Application\Navigation\HasNavigationSection;
use App\Application\Navigation\NavigationSection;
use App\Application\OpenGraph\HasOpenGraph;
use App\Application\OpenGraph\OpenGraph;
use App\Infrastructure\Cache\Cacheability;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class ResolvedFragment implements Fragment, HasNavigationSection, HasOpenGraph
{
    /**
     * @param \Closure(): ?string $render
     */
    public function __construct(
        private string $path,
        private Cacheability $cacheability,
        private \Closure $render,
        private FragmentType $type = FragmentType::PAGE,
        private ?NavigationSection $navigationSection = null,
        private ?OpenGraph $openGraph = null,
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

    public function getNavigationSection(): ?NavigationSection
    {
        return $this->navigationSection;
    }

    public function getOpenGraph(): ?OpenGraph
    {
        return $this->openGraph;
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
