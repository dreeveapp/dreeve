<?php

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use Twig\Environment;

final readonly class NotFoundFragment implements Fragment
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'not-found';
    }

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::empty(),
        );
    }

    public function render(): string
    {
        return $this->twig->load('html/not-found.html.twig')->render();
    }
}
