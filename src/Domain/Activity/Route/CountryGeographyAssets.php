<?php

declare(strict_types=1);

namespace App\Domain\Activity\Route;

use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\BoundingBox;

final class CountryGeographyAssets
{
    /** @var list<array{countryCode: string, polygonIndex: int, boundingBox: BoundingBox}>|null */
    private ?array $index = null;

    private readonly string $assetsDirectory;

    public function __construct()
    {
        $this->assetsDirectory = __DIR__.'/assets/countries';
    }

    /**
     * @return list<array{countryCode: string, polygonIndex: int, boundingBox: BoundingBox}>
     */
    public function index(): array
    {
        if (null !== $this->index) {
            return $this->index;
        }

        /** @var list<array{countryCode: string, polygonIndex: int, boundingBox: array{float, float, float, float}}> $rawIndex */
        $rawIndex = Json::decode($this->readAsset('index.json'));

        return $this->index = array_map(
            fn (array $entry): array => [
                ...$entry,
                'boundingBox' => BoundingBox::fromArray($entry['boundingBox']),
            ],
            $rawIndex
        );
    }

    public function has(string $countryCode): bool
    {
        return is_file($this->assetsDirectory.'/'.$countryCode.'.json');
    }

    /**
     * @return array<int, list<list<array{float, float}>>>
     */
    public function polygonsFor(string $countryCode): array
    {
        return Json::decode($this->readAsset($countryCode.'.json'));
    }

    private function readAsset(string $fileName): string
    {
        $path = $this->assetsDirectory.'/'.$fileName;

        if (!is_file($path) || false === $contents = file_get_contents($path)) {
            throw new \RuntimeException(sprintf('Country boundary asset "%s" is missing or unreadable. Rebuild it with "make build-countries-asset".', $path)); // @codeCoverageIgnore
        }

        return $contents;
    }
}
