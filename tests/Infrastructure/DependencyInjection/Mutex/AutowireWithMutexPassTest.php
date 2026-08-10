<?php

namespace App\Tests\Infrastructure\DependencyInjection\Mutex;

use App\Infrastructure\DependencyInjection\Mutex\AutowireWithMutexPass;
use App\Infrastructure\Mutex\Mutex;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AutowireWithMutexPassTest extends TestCase
{
    public function testProcess(): void
    {
        $container = $this->getFunctionalContainer();

        $container->registerForAutoconfiguration(DummyService::class)
            ->setProperty('fake', 'dummy');

        $container->register('dummy_service', DummyService::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setPublic(true)
            ->addTag('app.mutex', ['mutex' => 'mutex.importData']);
        $container->compile();

        $this->assertEquals('mutex.importData', (string) $container->getDefinition('dummy_service')->getArgument(0));
        $this->assertEquals(
            Mutex::class,
            $container->getDefinition('mutex.importData')->getClass()
        );
    }

    private function getFunctionalContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new AutowireWithMutexPass());

        // Disable removing passes to be able to inspect the container before all the inlining optimizations
        $container->getCompilerPassConfig()->setRemovingPasses([]);

        return $container;
    }
}

final readonly class DummyService
{
    public function __construct(private Mutex $mutex)
    {
    }

    public function getMutex(): Mutex
    {
        return $this->mutex;
    }
}
