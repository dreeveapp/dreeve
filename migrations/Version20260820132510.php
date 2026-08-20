<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Activity\Stream\StreamType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260820132510 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'DELETE FROM CombinedActivityStream WHERE activityId IN (
                SELECT activityId FROM ActivityStream WHERE streamType = :streamType AND dataSize > 0
            )',
            ['streamType' => StreamType::TEMP->value]
        );
    }

    public function down(Schema $schema): void
    {
    }
}
