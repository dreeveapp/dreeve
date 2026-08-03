<?php

declare(strict_types=1);

namespace App\Application\Import\StravaImport\ImportActivities;

use App\Domain\Activity\ActivityIds;
use App\Infrastructure\CQRS\Command\DomainCommand;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ImportActivities extends DomainCommand
{
    public function __construct(
        private OutputInterface $output,
        private ?ActivityIds $restrictToActivityIds,
    ) {
    }

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function getRestrictToActivityIds(): ActivityIds
    {
        return $this->restrictToActivityIds ?? ActivityIds::empty();
    }

    public function isFullImport(): bool
    {
        return $this->getRestrictToActivityIds()->isEmpty();
    }
}
