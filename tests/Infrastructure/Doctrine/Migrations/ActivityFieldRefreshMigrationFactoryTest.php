<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Migrations;

use App\Tests\ContainerTestCase;
use Doctrine\Migrations\DependencyFactory;

class ActivityFieldRefreshMigrationFactoryTest extends ContainerTestCase
{
    public function testItOnlyBuildsActivityFieldRefreshMigrationsWithActivityFieldsToRefresh(): void
    {
        /** @var DependencyFactory $dependencyFactory */
        $dependencyFactory = $this->getContainer()->get('doctrine.migrations.dependency_factory');
        $migrationFactory = $dependencyFactory->getMigrationFactory();

        $this->assertInstanceOf(
            ActivityFieldRefreshMigrationStub::class,
            $migrationFactory->createVersion(ActivityFieldRefreshMigrationStub::class),
        );
        $this->assertInstanceOf(
            MigrationStub::class,
            $migrationFactory->createVersion(MigrationStub::class),
        );
    }
}
