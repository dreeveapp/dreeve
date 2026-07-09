<?php

namespace App;

use App\Infrastructure\DependencyInjection\CQRS\RegisterDeserializableCommandsPass;
use App\Infrastructure\DependencyInjection\Mutex\AutowireWithMutexPass;
use App\Infrastructure\DependencyInjection\Mutex\WithMutex;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Theme\Theme;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerAttributeForAutoconfiguration(WithMutex::class, static function (ChildDefinition $definition, WithMutex $attribute): void {
            $definition->addTag('app.mutex', [
                'mutex' => sprintf('mutex.%s', $attribute->getLockName()->value),
            ]);
        });

        $container->addCompilerPass(new AutowireWithMutexPass());
        $container->addCompilerPass(new RegisterDeserializableCommandsPass());
    }

    #[\Override]
    protected function initializeContainer(): void
    {
        parent::initializeContainer();
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        assert($keyValueStore instanceof KeyValueStore);
        Theme::setKeyValueStore($keyValueStore);
    }
}
