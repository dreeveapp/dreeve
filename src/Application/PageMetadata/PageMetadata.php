<?php

declare(strict_types=1);

namespace App\Application\PageMetadata;

use Symfony\Contracts\Translation\TranslatableInterface;

final readonly class PageMetadata
{
    public function __construct(
        private string|TranslatableInterface $title,
        private string|TranslatableInterface|null $description = null,
        private ?string $imagePath = null,
    ) {
    }

    public function getTitle(): string|TranslatableInterface
    {
        return $this->title;
    }

    public function getDescription(): string|TranslatableInterface|null
    {
        return $this->description;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }
}
