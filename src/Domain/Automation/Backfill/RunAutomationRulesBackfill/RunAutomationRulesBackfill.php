<?php

declare(strict_types=1);

namespace App\Domain\Automation\Backfill\RunAutomationRulesBackfill;

use App\Infrastructure\CQRS\Command\DomainCommand;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class RunAutomationRulesBackfill extends DomainCommand
{
    public function __construct(
        private OutputInterface $output,
    ) {
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }
}
