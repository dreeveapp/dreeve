<?php

namespace App\Tests\Console;

use App\Tests\ContainerTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperInterface;
use Symfony\Component\Console\Helper\HelperSet;

abstract class ConsoleCommandTestCase extends ContainerTestCase
{
    private Application $application;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->application = new Application();
    }

    /**
     * @param array<string, HelperInterface> $helpers
     */
    public function getCommandInApplication(string $name, array $helpers = []): Command
    {
        $this->application->addCommand($this->getConsoleCommand());
        $command = $this->application->find($name);
        $helperSet = $command->getHelperSet();
        assert($helperSet instanceof HelperSet);

        foreach ($helpers as $alias => $helper) {
            $helperSet->set($helper, $alias);
        }

        return $command;
    }

    abstract protected function getConsoleCommand(): Command;
}
