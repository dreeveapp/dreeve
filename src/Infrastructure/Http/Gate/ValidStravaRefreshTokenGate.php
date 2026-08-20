<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Gate;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Import\ImportMode;
use App\Domain\Strava\InsufficientStravaAccessTokenScopes;
use App\Domain\Strava\InvalidStravaAccessToken;
use App\Domain\Strava\Strava;
use App\Domain\Strava\StravaApplicationIsInactive;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsTaggedItem(priority: 90)]
final class ValidStravaRefreshTokenGate extends ConditionalRedirectGate
{
    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        private readonly ImportMode $importMode,
        private readonly Strava $strava,
        private readonly ActivityIdRepository $activityIdRepository,
    ) {
        parent::__construct($urlGenerator);
    }

    protected function shouldGuard(): bool
    {
        if (!$this->importMode->isStravaApi()) {
            return false;
        }

        if ($this->activityIdRepository->hasImportedFromStravaApi()) {
            return false;
        }

        try {
            $this->strava->verifyAccessToken();

            return false;
        } catch (InvalidStravaAccessToken|InsufficientStravaAccessTokenScopes|StravaApplicationIsInactive) {
            return true;
        }
    }

    protected function allowedPaths(): array
    {
        return [];
    }

    protected function redirectToRouteName(): string
    {
        return 'strava_oauth';
    }
}
