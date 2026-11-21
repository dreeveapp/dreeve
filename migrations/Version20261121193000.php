<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Domain\Activity\Activity;
use App\Infrastructure\Doctrine\Migrations\ActivityFileRefreshMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20261121193000 extends ActivityFileRefreshMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE Activity ADD COLUMN descent INTEGER DEFAULT NULL');
        parent::up($schema);
    }

    protected function columnsToRefresh(Activity $parsedActivity): array
    {
        return ['descent' => $parsedActivity->getDescent()?->toInt()];
    }

    public function down(Schema $schema): void
    {
    }
}
