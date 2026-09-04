<?php

declare(strict_types=1);

namespace App\Tests\Domain\Integration\Weather\OpenMeteo;

use App\Domain\Integration\Weather\OpenMeteo\OpenMeteo;
use App\Domain\Integration\Weather\OpenMeteo\OpenMeteoArchiveApiCallHasFailed;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

class SpyOpenMeteo implements OpenMeteo
{
    private bool $triggerExceptionOnNextCall = false;
    /** @var array<mixed> */
    private array $weatherStats = [];

    public function getWeatherStats(Coordinate $coordinate, SerializableDateTime $date): array
    {
        if ($this->triggerExceptionOnNextCall) {
            $this->triggerExceptionOnNextCall = false;
            throw new OpenMeteoArchiveApiCallHasFailed();
        }

        return $this->weatherStats;
    }

    public function returnHourlyWeatherStats(): void
    {
        $this->weatherStats = Json::decode(file_get_contents(__DIR__.'/fixtures/hourly-weather-stats.json') ?: '{}');
    }

    public function triggerExceptionOnNextCall(): void
    {
        $this->triggerExceptionOnNextCall = true;
    }
}
