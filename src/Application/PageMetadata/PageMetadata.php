<?php

declare(strict_types=1);

namespace App\Application\PageMetadata;

use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class PageMetadata
{
    /**
     * @param array<string, string|int> $routeParams
     */
    public function __construct(
        private string $route,
        private string|TranslatableInterface $title,
        private string|TranslatableInterface $description,
        private array $routeParams = [],
        private ?string $imageRoute = null,
    ) {
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return array<string, string|int>
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    public function getTitle(): string|TranslatableInterface
    {
        return $this->title;
    }

    public function getDescription(): string|TranslatableInterface
    {
        return $this->description;
    }

    public function getImageRoute(): ?string
    {
        return $this->imageRoute;
    }
}
