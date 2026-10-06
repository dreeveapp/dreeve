<?php

declare(strict_types=1);

namespace App\Application\Import\FileImport\ImportActivityFiles;

use App\Application\Import\ImportedActivities;
use App\Infrastructure\CQRS\Command\DomainCommand;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ImportActivityFiles extends DomainCommand
{
    public function __construct(
        private OutputInterface $output,
        private ImportedActivities $importedActivities,
    ) {
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function getImportedActivities(): ImportedActivities
    {
        return $this->importedActivities;
    }
}
