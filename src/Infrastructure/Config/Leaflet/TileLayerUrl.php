<?php

declare(strict_types=1);

namespace App\Infrastructure\Config\Leaflet;

use App\Infrastructure\ValueObject\String\Url;

final readonly class TileLayerUrl extends Url
{
    #[\Override]
    protected function validate(string $value): void
    {
        parent::validate($value);

        foreach (['{z}', '{x}', '{y}'] as $placeholder) {
            if (!str_contains($value, $placeholder)) {
                throw new \InvalidArgumentException(sprintf('Invalid tile layer url "%s", it must contain the placeholders {z}, {x} and {y}', $value));
            }
        }
    }
}
