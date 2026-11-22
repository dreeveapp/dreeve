<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Migrations;

use App\Infrastructure\KeyValue\KeyValueStore;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Version\MigrationFactory;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator('doctrine.migrations.migrations_factory')]
final readonly class KeyValueStoreAwareMigrationFactory implements MigrationFactory
{
    public function __construct(
        #[AutowireDecorated]
        private MigrationFactory $migrationFactory,
        #[Autowire(service: 'doctrine.migrations.dependency_factory')]
        private DependencyFactory $dependencyFactory,
        private KeyValueStore $keyValueStore,
    ) {
    }

    public function createVersion(string $migrationClassName): AbstractMigration
    {
        if (!is_subclass_of($migrationClassName, KeyValueStoreAwareMigration::class)) {
            return $this->migrationFactory->createVersion($migrationClassName);
        }

        return new $migrationClassName(
            connection: $this->dependencyFactory->getConnection(),
            logger: $this->dependencyFactory->getLogger(),
            keyValueStore: $this->keyValueStore,
        );
    }
}
