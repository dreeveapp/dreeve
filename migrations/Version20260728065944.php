<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\Stream\Metric\ActivityStreamMetricType;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260728065944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $sportTypes = [
            SportType::SAIL->value,
            SportType::WIND_SURF->value,
            SportType::KITE_SURF->value,
        ];

        $this->addSql(
            'DELETE FROM CombinedActivityStream WHERE activityId IN (
                SELECT activityId FROM Activity WHERE sportType IN (:sportTypes)
            )',
            ['sportTypes' => $sportTypes],
            ['sportTypes' => ArrayParameterType::STRING]
        );

        $this->addSql(
            'DELETE FROM ActivityStreamMetric WHERE metricType = :metricType AND activityId IN (
                SELECT activityId FROM Activity WHERE sportType IN (:sportTypes)
            )',
            [
                'metricType' => ActivityStreamMetricType::VALUE_DISTRIBUTION->value,
                'sportTypes' => $sportTypes,
            ],
            ['sportTypes' => ArrayParameterType::STRING]
        );
    }

    public function down(Schema $schema): void
    {
    }
}
