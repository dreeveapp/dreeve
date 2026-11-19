<?php

declare(strict_types=1);

namespace App\Domain\Media\UpdateMediaItem;

use App\Domain\Media\MediaItemRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;

final readonly class UpdateMediaItemCommandHandler implements CommandHandler
{
    public function __construct(
        private MediaItemRepository $mediaItemRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof UpdateMediaItem);

        $this->mediaItemRepository->update(
            $this->mediaItemRepository->find($command->getMediaItemId())
                ->withCoordinate($command->getCoordinate())
                ->withTakenOn($command->getTakenOn())
        );
    }
}
