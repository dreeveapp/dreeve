<?php

namespace App;

use App\Domain\Import\ImportMode;
use App\Infrastructure\Config\AppConfig;
use App\Infrastructure\Config\PlatformEnvironment;
use App\Infrastructure\DependencyInjection\AppExpressionLanguageProvider;
use App\Infrastructure\DependencyInjection\CQRS\RegisterDeserializableCommandsPass;
use App\Infrastructure\DependencyInjection\Mutex\AutowireWithMutexPass;
use App\Infrastructure\DependencyInjection\Mutex\WithMutex;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\Theme\Theme;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
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
        $container->addExpressionLanguageProvider(new AppExpressionLanguageProvider());
    }

    #[\Override]
    protected function initializeContainer(): void
    {
        parent::initializeContainer();
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        assert($keyValueStore instanceof KeyValueStore);
        Theme::setKeyValueStore($keyValueStore);

        $importMode = $this->getContainer()->get(ImportMode::class);
        assert($importMode instanceof ImportMode);
        $kernelProjectDir = $this->getContainer()->get(KernelProjectDir::class);
        assert($kernelProjectDir instanceof KernelProjectDir);
        $platformEnvironment = $this->getContainer()->get(PlatformEnvironment::class);
        assert($platformEnvironment instanceof PlatformEnvironment);
        AppConfig::setYamlConfigFilesToParse($kernelProjectDir, $platformEnvironment);
    }
}
