<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\EnrichActivity;

use App\Domain\Integration\Weather\OpenMeteo\OpenMeteo;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class RespondingOpenMeteoStub implements OpenMeteo
{
    public function getWeatherStats(Coordinate $coordinate, SerializableDateTime $date): array
    {
        return Json::decode(file_get_contents(__DIR__.'/fixtures/open-meteo-response.json') ?: '{}');
    }
}
