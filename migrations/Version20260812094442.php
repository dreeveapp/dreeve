<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260812094442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX Activity_importSource ON Activity (importSource)');
        $this->addSql('CREATE INDEX ActivityStreamMetric_streamTypeMetricType ON ActivityStreamMetric (streamType, metricType)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX Activity_importSource');
        $this->addSql('DROP INDEX ActivityStreamMetric_streamTypeMetricType');
    }
}
