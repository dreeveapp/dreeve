<?php

namespace App\Domain\Integration\Weather\OpenMeteo;

use App\Infrastructure\Measurement\Temperature\Celsius;
use App\Infrastructure\Measurement\Velocity\KmPerHour;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class Weather implements \JsonSerializable
{
    /**
     * @param array<mixed> $data
     */
    private function __construct(
        private array $data,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromState(array $data): self
    {
        return new self($data);
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromRawData(array $data, SerializableDateTime $on): ?self
    {
        $hour = $on->getHourWithoutLeadingZero();
        if (empty($data['hourly']['time'][$hour])) {
            return null;
        }

        // Use weather known for the given hour.
        $weather = [];
        foreach ($data['hourly'] as $metric => $values) {
            $weather[$metric] = $values[$hour];
        }

        return new self($weather);
    }

    public function getTemperatureInCelsius(): Celsius
    {
        return Celsius::from($this->data['temperature_2m']);
    }

    public function getRelativeHumidity(): float
    {
        return $this->data['relativehumidity_2m'];
    }

    public function getWindSpeed(): KmPerHour
    {
        return KmPerHour::from($this->data['windspeed_10m']);
    }

    /**
     * @codeCoverageIgnore
     */
    public function getWindDirection(): string
    {
        $degrees = $this->data['winddirection_10m'];

        return match (true) {
            $degrees >= 348.75,
            $degrees >= 0 && $degrees < 33.75 => 'N',
            $degrees >= 33.75 && $degrees < 78.75 => 'NE',
            $degrees >= 78.75 && $degrees < 123.75 => 'E',
            $degrees >= 123.75 && $degrees < 168.75 => 'SE',
            $degrees >= 168.75 && $degrees < 213.75 => 'S',
            $degrees >= 213.75 && $degrees < 258.75 => 'SW',
            $degrees >= 258.75 && $degrees < 303.75 => 'W',
            $degrees >= 303.75 && $degrees < 348.75 => 'NW',
            default => throw new \RuntimeException('What world do you live in??'),
        };
    }

    /**
     * @codeCoverageIgnore
     */
    public function getWeatherCodeDescription(): string
    {
        $weatherCode = $this->data['weathercode'];

        // https://www.nodc.noaa.gov/archive/arc0021/0002199/1.1/data/0-data/HTML/WMO-CODE/WMO4677.HTM
        return match ($weatherCode) {
            0,1,2 => 'Clear',
            3 => 'Cloudy',
            4 => 'Reduced visibility',
            5 => 'Hazy',
            6,7 ,30,31,32,33,34,35 => 'Dusty',
            8,9 => 'Sandstorm',
            10 => 'Misty',
            11,12,28,40,41,42,43,44,45,46,47,48,49 => 'Foggy',
            13,14,15,16,17,18,19,27,29,89,90,95,96,97,98,99 => 'Stormy',
            20,22,23,24,26,36,37,38,39,70,71,72,73,74,75,76,77,78,85,86,87,88,94 => 'Snowy',
            21,25,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67,68,69,80,81,82,83,84,91,92,93 => 'Rainy',
            79 => 'Cold',
            default => throw new \RuntimeException('Unsupported weather code'),
        };
    }
}
