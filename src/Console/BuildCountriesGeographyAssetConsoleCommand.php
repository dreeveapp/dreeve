<?php

declare(strict_types=1);

namespace App\Console;

use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Polygon;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use GuzzleHttp\Client;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:geography:build-countries-asset', description: 'Build the country boundary asset from Natural Earth')]
final class BuildCountriesGeographyAssetConsoleCommand extends Command
{
    private const int COORDINATE_PRECISION = 5;
    private const string NATURAL_EARTH_VERSION = 'v5.1.2';
    private const string SOURCE_URL = 'https://raw.githubusercontent.com/nvkelso/natural-earth-vector/'
        .self::NATURAL_EARTH_VERSION.'/geojson/ne_10m_admin_0_countries.geojson';

    public function __construct(
        private readonly KernelProjectDir $kernelProjectDir,
        private readonly Client $client,
        private readonly FilesystemOperator $countriesGeographyStorage,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = $this->kernelProjectDir.'/var/ne_10m_admin_0_countries.geojson';

        $output->writeln(sprintf('=> Downloading Natural Earth %s', self::NATURAL_EARTH_VERSION));
        $this->client->request('GET', self::SOURCE_URL, ['sink' => $source]);

        try {
            $this->build($source, $output);
        } finally {
            unlink($source);
        }

        return Command::SUCCESS;
    }

    private function build(string $source, OutputInterface $output): void
    {
        foreach ($this->countriesGeographyStorage->listContents('') as $staleFile) {
            $this->countriesGeographyStorage->delete($staleFile->path());
        }

        /** @var array<string, list<string>> $encodedPolygonsPerCountry */
        $encodedPolygonsPerCountry = [];
        $index = [];
        $totalVertices = 0;

        foreach (Json::decodeLazyFromFile($source, '/features') as $feature) {
            $countryCode = $feature['properties']['ISO_A2_EH'] ?? null;
            if (!is_string($countryCode)) {
                continue;
            }
            if (1 !== preg_match('/^[A-Z]{2}$/', $countryCode)) {
                continue;
            }

            $polygons = 'Polygon' === $feature['geometry']['type']
                ? [$feature['geometry']['coordinates']]
                : $feature['geometry']['coordinates'];

            foreach ($polygons as $polygon) {
                $polygon = $this->toRoundedFloatRings($polygon);
                if ([] === ($polygon[0] ?? [])) {
                    continue; // @codeCoverageIgnore
                }

                $index[] = [
                    'countryCode' => $countryCode,
                    'polygonIndex' => count($encodedPolygonsPerCountry[$countryCode] ?? []),
                    'boundingBox' => Polygon::fromLngLatRings($polygon)->boundingBox(),
                ];
                $totalVertices += array_sum(array_map(count(...), $polygon));
                $encodedPolygonsPerCountry[$countryCode][] = Json::encode($polygon);
            }
        }
        ksort($encodedPolygonsPerCountry);
        usort($index, fn (array $a, array $b): int => [$a['countryCode'], $a['polygonIndex']] <=> [$b['countryCode'], $b['polygonIndex']]);

        $totalPolygons = 0;
        foreach ($encodedPolygonsPerCountry as $countryCode => $encodedPolygons) {
            $totalPolygons += count($encodedPolygons);
            // Each element is already a complete JSON array, so this is the encoding of the list.
            $this->countriesGeographyStorage->write(
                $countryCode.'.json',
                '['.implode(',', $encodedPolygons).']'
            );
        }

        $this->countriesGeographyStorage->write('index.json', Json::encode($index));

        $bytes = 0;
        foreach ($this->countriesGeographyStorage->listContents('') as $file) {
            $bytes += $this->countriesGeographyStorage->fileSize($file->path());
        }

        $output->writeln(sprintf(
            '=> Wrote %d countries, %d polygons, %d vertices (%.1f MB)',
            count($encodedPolygonsPerCountry),
            $totalPolygons,
            $totalVertices,
            $bytes / 1024 / 1024
        ));
    }

    /**
     * @param list<list<array{float, float}>> $polygon
     *
     * @return list<list<array{float, float}>>
     */
    private function toRoundedFloatRings(array $polygon): array
    {
        return array_map(
            fn (array $ring): array => array_map(
                fn (array $coordinate): array => [
                    round((float) $coordinate[0], self::COORDINATE_PRECISION),
                    round((float) $coordinate[1], self::COORDINATE_PRECISION),
                ],
                $ring
            ),
            $polygon
        );
    }
}
