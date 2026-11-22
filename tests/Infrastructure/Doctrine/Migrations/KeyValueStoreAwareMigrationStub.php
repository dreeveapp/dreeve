<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Migrations;

use App\Infrastructure\Doctrine\Migrations\KeyValueStoreAwareMigration;
use Doctrine\DBAL\Schema\Schema;

final class KeyValueStoreAwareMigrationStub extends KeyValueStoreAwareMigration
{
    public function up(Schema $schema): void
    {
    }
}
