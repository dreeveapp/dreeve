<?php

declare(strict_types=1);

namespace App\Infrastructure\DependencyInjection\CQRS;

use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommandRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;

final class RegisterDeserializableCommandsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $srcDir = $container->getParameter('kernel.project_dir');
        $srcDir .= '/src';
        if (!is_dir($srcDir)) {
            return;
        }

        $commandsByName = [];
        foreach (new Finder()->files()->in($srcDir)->name('*.php') as $file) {
            if (!str_contains($file->getContents(), 'DeserializableCommand')) {
                continue;
            }

            $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            if (!class_exists($class)) {
                continue;
            }
            if (!is_a($class, DeserializableCommand::class, true)) {
                continue;
            }
            if (new \ReflectionClass($class)->isAbstract()) {
                continue;
            }

            $name = $class::getCommandName();
            if (isset($commandsByName[$name])) {
                throw new \LogicException(sprintf('Duplicate deserializable command name "%s" on "%s" and "%s".', $name, $commandsByName[$name], $class));
            }
            $commandsByName[$name] = $class;
        }

        $container->getDefinition(DeserializableCommandRegistry::class)->setArgument(0, $commandsByName);
    }
}
