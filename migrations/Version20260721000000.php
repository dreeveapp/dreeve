<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Activity\Stream\StreamType;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Geography\Polyline;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Recompute over-simplified polylines for file-imported activities (fit/tcx/gpx). '
            .'A bug used a simplify() tolerance of 0.4 degrees (~44km), collapsing imported routes '
            .'to a straight 2-point line. This recomputes the polyline from the still-intact latlng '
            .'stream so users do not have to re-import. Strava API polylines are left untouched.';
    }

    public function up(Schema $schema): void
    {
        $activities = $this->connection->executeQuery(
            'SELECT activityId FROM Activity WHERE importSource IN (:importSources)',
            ['importSources' => ['fitFile', 'tcxFile', 'gpxFile']],
            ['importSources' => \Doctrine\DBAL\ArrayParameterType::STRING],
        )->fetchFirstColumn();

        foreach ($activities as $activityId) {
            $data = $this->connection->executeQuery(
                'SELECT data FROM ActivityStream WHERE activityId = :activityId AND streamType = :streamType',
                [
                    'activityId' => $activityId,
                    'streamType' => StreamType::LAT_LNG->value,
                ],
            )->fetchOne();

            if (false === $data) {
                // No latlng stream stored for this activity, nothing to recompute.
                continue;
            }

            /** @var array<int, array{float, float}> $coordinates */
            $coordinates = array_values(array_filter(
                Json::uncompressAndDecode($data),
                is_array(...),
            ));

            if ([] === $coordinates) {
                continue;
            }

            $this->connection->executeStatement(
                'UPDATE Activity SET polyline = :polyline WHERE activityId = :activityId',
                [
                    // Pin the tolerance explicitly so this historical migration stays frozen
                    // against future changes to the Polyline::simplify() default.
                    'polyline' => (string) Polyline::fromCoordinates($coordinates)->simplify(0.0001)->encode(),
                    'activityId' => $activityId,
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Irreversible data migration: the original collapsed polylines were corrupt (a straight
        // 2-point line) and cannot — and should not — be restored.
    }
}
