<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser\Gpx;

final readonly class GpxMetadata
{
    private function __construct(
        private ?string $name,
        private ?string $description,
    ) {
    }

    public static function fromXml(\SimpleXMLElement $gpx): self
    {
        if (!property_exists($gpx, 'metadata') || null === $gpx->metadata) {
            return new self(name: null, description: null);
        }

        return new self(
            name: self::stringChild($gpx->metadata, 'name'),
            description: self::stringChild($gpx->metadata, 'desc'),
        );
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    private static function stringChild(\SimpleXMLElement $parent, string $child): ?string
    {
        if (!property_exists($parent, $child) || null === $parent->{$child}) {
            return null;
        }

        return '' !== ($value = trim((string) $parent->{$child})) ? $value : null;
    }
}
