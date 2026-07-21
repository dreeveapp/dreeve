<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\Geography\Polyline;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721135509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $results = $this->connection->fetchAllAssociative(
            'SELECT Activity.activityId, Activity.polyline, ActivityStream.data AS latLngStream
                FROM Activity
                INNER JOIN ActivityStream ON ActivityStream.activityId = Activity.activityId
                    AND ActivityStream.streamType = :streamType
                WHERE Activity.polyline IS NOT NULL AND Activity.polyline != ""',
            ['streamType' => StreamType::LAT_LNG->value]
        );

        foreach ($results as $result) {
            if (count(EncodedPolyline::fromString($result['polyline'])->decodeAndPairLatLng()) >= 10) {
                // Polyline was not collapsed, nothing to repair.
                continue;
            }

            try {
                $latLngStream = Json::uncompressAndDecode($result['latLngStream']);
            } catch (\JsonException) {
                continue;
            }

            /** @var array<int, array{float, float}> $coordinates */
            $coordinates = array_values(array_filter(
                is_array($latLngStream) ? $latLngStream : [],
                is_array(...),
            ));
            if (count($coordinates) < 10) {
                // The polyline is legitimately this small.
                continue;
            }

            $this->addSql(
                'UPDATE Activity SET polyline = :polyline WHERE activityId = :activityId',
                [
                    'polyline' => (string) Polyline::fromCoordinates($coordinates)->simplify()->encode(),
                    'activityId' => $result['activityId'],
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
    }
}
