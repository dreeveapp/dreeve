<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Migrations\ActivityFieldRefresh;

use App\Infrastructure\Doctrine\Migrations\ActivityFieldRefresh\ActivityFieldRefreshMigration;
use Doctrine\DBAL\Schema\Schema;

final class ActivityFieldRefreshMigrationStub extends ActivityFieldRefreshMigration
{
    public function up(Schema $schema): void
    {
    }
}
