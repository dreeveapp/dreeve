<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\EnrichActivity;

use App\Domain\Integration\Weather\OpenMeteo\OpenMeteo;
use App\Domain\Integration\Weather\OpenMeteo\OpenMeteoArchiveApiCallHasFailed;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class UnreachableOpenMeteoStub implements OpenMeteo
{
    public function getWeatherStats(Coordinate $coordinate, SerializableDateTime $date): array
    {
        throw new OpenMeteoArchiveApiCallHasFailed();
    }
}
