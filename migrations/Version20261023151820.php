<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023151820 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE Activity
            SET routeGeography = JSON_SET(routeGeography, '$.state', LOWER(REPLACE(JSON_EXTRACT(routeGeography, '$.state'), ' ', '-')))
            WHERE worldType = 'zwift' AND JSON_EXTRACT(routeGeography, '$.state') IS NOT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
    }
}
