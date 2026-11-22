<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Migrations;

use App\Infrastructure\Doctrine\Migrations\ActivityFieldRefreshMigration;
use Doctrine\DBAL\Schema\Schema;

final class ActivityFieldRefreshMigrationStub extends ActivityFieldRefreshMigration
{
    public function up(Schema $schema): void
    {
    }
}
