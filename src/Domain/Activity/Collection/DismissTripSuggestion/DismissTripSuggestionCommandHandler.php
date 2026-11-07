<?php

declare(strict_types=1);

namespace App\Domain\Activity\Collection\DismissTripSuggestion;

use App\Domain\Activity\Collection\DismissedTripSuggestionRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class DismissTripSuggestionCommandHandler implements CommandHandler
{
    public function __construct(
        private DismissedTripSuggestionRepository $dismissedTripSuggestionRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof DismissTripSuggestion);

        $this->dismissedTripSuggestionRepository->add($command->getActivityIds());
    }
}
