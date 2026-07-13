<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Gate;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Import\ImportMode;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsTaggedItem(priority: 70)]
final class AppHasBeenBuiltGate extends ConditionalRedirectGate
{
    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        private readonly ImportMode $importMode,
        private readonly FilesystemOperator $buildHtmlStorage,
        private readonly ActivityIdRepository $activityIdRepository,
    ) {
        parent::__construct($urlGenerator);
    }

    protected function shouldGuard(): bool
    {
        if (!$this->buildHtmlStorage->fileExists('index.html')) {
            return true;
        }

        return $this->activityIdRepository->count() <= 0;
    }

    protected function allowedPaths(): array
    {
        if (!$this->importMode->isFiles()) {
            // In Strava API mode the user still has to walk through the remaining setup steps.
            return [];
        }

        // Keep the admin panel reachable, it is the only way to upload files.
        return ['/admin'];
    }

    protected function redirectToRouteName(): string
    {
        return 'finish_setup';
    }
}
