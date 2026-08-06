<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use App\Domain\Gear\UpdateGear\UpdateGear;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'tmp:rename-gear')]
final class TmpRenameGearCommand extends Command
{
    public function __construct(
        private readonly CommandBus $commandBus,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commandBus->dispatch(UpdateGear::fromPayload([
            'gearId' => (string) $input->getArgument('gearId'),
            'name' => (string) $input->getArgument('name'),
        ]));
        $output->writeln('renamed');

        return Command::SUCCESS;
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('gearId')->addArgument('name');
    }
}
