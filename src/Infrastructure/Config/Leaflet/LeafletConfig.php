<?php

declare(strict_types=1);

namespace App\Infrastructure\Config\Leaflet;

use App\Infrastructure\ValueObject\String\CssColor;
use App\Infrastructure\ValueObject\String\Url;

final readonly class LeafletConfig implements \JsonSerializable
{
    private function __construct(
        private CssColor $polylineColor,
        /** @var Url[] */
        private array $tileLayerUrls,
        private bool $enableGreyScale,
    ) {
    }

    /**
     * @param string[] $tileLayerUrls
     */
    public static function create(
        string $polylineColor,
        array $tileLayerUrls,
        bool $enableGreyScale,
    ): self {
        return new self(
            polylineColor: CssColor::fromString($polylineColor),
            tileLayerUrls: array_map(Url::fromString(...), $tileLayerUrls),
            enableGreyScale: $enableGreyScale,
        );
    }

    public function getPolylineColor(): CssColor
    {
        return $this->polylineColor;
    }

    /**
     * @return Url[]
     */
    public function getTileLayerUrls(): array
    {
        return $this->tileLayerUrls;
    }

    public function enableGreyScale(): bool
    {
        return $this->enableGreyScale;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'polylineColor' => $this->getPolylineColor(),
            'tileLayerUrls' => $this->getTileLayerUrls(),
            'enableGreyScale' => $this->enableGreyScale(),
        ];
    }
}
