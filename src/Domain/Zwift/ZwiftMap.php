<?php

declare(strict_types=1);

namespace App\Domain\Zwift;

use App\Domain\Activity\LeafletMap;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;

final readonly class ZwiftMap implements LeafletMap
{
    private const string BOLOGNA = 'bologna';
    private const string CRIT_CITY = 'crit-city';
    private const string FRANCE = 'france';
    private const string INNSBRUCK = 'innsbruck';
    private const string LONDON = 'london';
    private const string MAKURI_ISLANDS = 'makuri-islands';
    private const string NEW_YORK = 'new-york';
    private const string PARIS = 'paris';
    private const string RICHMOND = 'richmond';
    private const string SCOTLAND = 'scotland';
    private const string WATOPIA = 'watopia';
    private const string YORKSHIRE = 'yorkshire';

    private function __construct(
        private string $mapName,
        /** @var Coordinate[] */
        private array $bounds,
    ) {
    }

    public function getId(): string
    {
        return $this->mapName;
    }

    public function getLabel(): string
    {
        return ucwords(str_replace('-', ' ', $this->mapName));
    }

    public function getTileLayer(): ?string
    {
        return null;
    }

    public function getOverlayImageUrl(): string
    {
        return '/assets/images/maps/zwift-'.$this->mapName.'.webp';
    }

    public function getBounds(): array
    {
        return $this->bounds;
    }

    public function getMaxZoom(): int
    {
        return 18;
    }

    public function getMinZoom(): int
    {
        return 'new-york' === $this->mapName ? 11 : 12;
    }

    public function getBackgroundColor(): string
    {
        return '#bbbbb7';
    }

    public static function fromId(string $id): self
    {
        return new self(
            mapName: $id,
            bounds: self::getBoundMap()[$id] ?? throw new CouldNotDetermineZwiftMap('Unknown Zwift map '.$id),
        );
    }

    public static function forStartingCoordinate(Coordinate $coordinate): self
    {
        foreach (self::getBoundMap() as $mapName => $bounds) {
            if ($coordinate->getLatitude()->toFloat() <= $bounds[0]->getLatitude()->toFloat()
                && $coordinate->getLatitude()->toFloat() >= $bounds[1]->getLatitude()->toFloat()
                && $coordinate->getLongitude()->toFloat() >= $bounds[0]->getLongitude()->toFloat()
                && $coordinate->getLongitude()->toFloat() <= $bounds[1]->getLongitude()->toFloat()) {
                return new ZwiftMap(
                    mapName: $mapName,
                    bounds: $bounds
                );
            }
        }

        throw new CouldNotDetermineZwiftMap('Could not determine Zwift map '.Json::encode($coordinate));
    }

    /**
     * @return array<string, Coordinate[]>
     */
    private static function getBoundMap(): array
    {
        // https://zwiftinsider.com/hilly-kom-bypass/
        // https://cf.veloviewer.com/js/vv.lmap.61.js
        // https://github.com/andipaetzold/zwift-data/blob/main/src/worlds.ts
        return [
            self::BOLOGNA => [
                Coordinate::createFromLatAndLng(Latitude::fromString('44.5308037'), Longitude::fromString('11.26261748')),
                Coordinate::createFromLatAndLng(Latitude::fromString('44.45463821'), Longitude::fromString('11.36991729102076')),
            ],
            self::CRIT_CITY => [
                Coordinate::createFromLatAndLng(Latitude::fromString('-10.3657'), Longitude::fromString('165.7824')),
                Coordinate::createFromLatAndLng(Latitude::fromString('-10.4038'), Longitude::fromString('165.8207')),
            ],
            self::FRANCE => [
                Coordinate::createFromLatAndLng(Latitude::fromString('-21.64155'), Longitude::fromString('166.1384')),
                Coordinate::createFromLatAndLng(Latitude::fromString('-21.7564'), Longitude::fromString('166.26125')),
            ],
            self::INNSBRUCK => [
                Coordinate::createFromLatAndLng(Latitude::fromString('47.2947'), Longitude::fromString('11.3501')),
                Coordinate::createFromLatAndLng(Latitude::fromString('47.2055'), Longitude::fromString('11.4822')),
            ],
            self::LONDON => [
                Coordinate::createFromLatAndLng(Latitude::fromString('51.5362'), Longitude::fromString('-0.1776')),
                Coordinate::createFromLatAndLng(Latitude::fromString('51.4601'), Longitude::fromString('-0.0555')),
            ],
            self::MAKURI_ISLANDS => [
                Coordinate::createFromLatAndLng(Latitude::fromString('-10.7375'), Longitude::fromString('165.7828')),
                Coordinate::createFromLatAndLng(Latitude::fromString('-10.831'), Longitude::fromString('165.8772')),
            ],
            self::NEW_YORK => [
                Coordinate::createFromLatAndLng(Latitude::fromString('40.81725'), Longitude::fromString('-74.0227')),
                Coordinate::createFromLatAndLng(Latitude::fromString('40.6385'), Longitude::fromString('-73.9222')),
            ],
            self::PARIS => [
                Coordinate::createFromLatAndLng(Latitude::fromString('48.9058'), Longitude::fromString('2.2561')),
                Coordinate::createFromLatAndLng(Latitude::fromString('48.82945'), Longitude::fromString('2.3722')),
            ],
            self::RICHMOND => [
                Coordinate::createFromLatAndLng(Latitude::fromString('37.5774'), Longitude::fromString('-77.48954')),
                Coordinate::createFromLatAndLng(Latitude::fromString('37.5014'), Longitude::fromString('-77.394')),
            ],
            self::SCOTLAND => [
                Coordinate::createFromLatAndLng(Latitude::fromString('55.675959999999996'), Longitude::fromString('-5.28053')),
                Coordinate::createFromLatAndLng(Latitude::fromString('55.6185'), Longitude::fromString('-5.17753')),
            ],
            self::WATOPIA => [
                Coordinate::createFromLatAndLng(Latitude::fromString('-11.626'), Longitude::fromString('166.87747')),
                Coordinate::createFromLatAndLng(Latitude::fromString('-11.729'), Longitude::fromString('167.03255')),
            ],
            self::YORKSHIRE => [
                Coordinate::createFromLatAndLng(Latitude::fromString('54.0254'), Longitude::fromString('-1.6320')),
                Coordinate::createFromLatAndLng(Latitude::fromString('53.9491'), Longitude::fromString('-1.5022')),
            ],
        ];
    }
}
