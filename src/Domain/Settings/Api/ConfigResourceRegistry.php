<?php

declare(strict_types=1);

namespace App\Domain\Settings\Api;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ConfigResourceRegistry
{
    /**
     * @param iterable<ConfigResource> $configResources
     */
    public function __construct(
        #[AutowireIterator('app.api.config_resource')]
        private iterable $configResources,
    ) {
    }

    /**
     * @throws CouldNotResolveConfigResource
     */
    public function resolve(string $name): ConfigResource
    {
        foreach ($this->configResources as $configResource) {
            if ($configResource->getName() === $name) {
                return $configResource;
            }
        }

        throw CouldNotResolveConfigResource::withName($name);
    }

    /**
     * @return list<ConfigResource>
     */
    public function findAll(): array
    {
        $configResources = [...$this->configResources];
        usort($configResources, fn (ConfigResource $a, ConfigResource $b): int => strcmp($a->getName(), $b->getName()));

        return $configResources;
    }
}
