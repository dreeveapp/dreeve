<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Migrations;

use App\Tests\ContainerTestCase;
use Doctrine\Migrations\DependencyFactory;

class KeyValueStoreAwareMigrationFactoryTest extends ContainerTestCase
{
    public function testItOnlyBuildsKeyValueStoreAwareMigrationsWithTheKeyValueStore(): void
    {
        /** @var DependencyFactory $dependencyFactory */
        $dependencyFactory = $this->getContainer()->get('doctrine.migrations.dependency_factory');
        $migrationFactory = $dependencyFactory->getMigrationFactory();

        $this->assertInstanceOf(
            KeyValueStoreAwareMigrationStub::class,
            $migrationFactory->createVersion(KeyValueStoreAwareMigrationStub::class),
        );
        $this->assertInstanceOf(
            MigrationStub::class,
            $migrationFactory->createVersion(MigrationStub::class),
        );
    }
}
