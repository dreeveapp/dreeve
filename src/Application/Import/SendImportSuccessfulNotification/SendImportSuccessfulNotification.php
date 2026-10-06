<?php

declare(strict_types=1);

namespace App\Application\Import\SendImportSuccessfulNotification;

use App\Application\Import\ImportedActivities;
use App\Infrastructure\CQRS\Command\DomainCommand;

final readonly class SendImportSuccessfulNotification extends DomainCommand
{
    public function __construct(
        private ImportedActivities $importedActivities,
    ) {
    }

    public function getImportedActivities(): ImportedActivities
    {
        return $this->importedActivities;
    }
}
