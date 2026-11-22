<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Import\RefreshFromFiles\RefreshableActivityField;
use App\Infrastructure\Doctrine\Migrations\ActivityFieldRefreshMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20261121193000 extends ActivityFieldRefreshMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Activity ADD COLUMN descent INTEGER DEFAULT NULL');
    }

    public function postUp(Schema $schema): void
    {
        $this->activityFieldsToRefresh->add(RefreshableActivityField::DESCENT);
    }

    public function down(Schema $schema): void
    {
    }
}
