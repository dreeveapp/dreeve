<?php

declare(strict_types=1);

namespace App\Controller\Api\Internal;

use App\Application\AppVersion;
use App\Domain\Integration\GitHub\GitHub;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Exception\CorruptedData;
use App\Infrastructure\Serialization\Json;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class LatestReleaseRequestHandler
{
    private const int SIX_HOURS_IN_SECONDS = 6 * 3600;

    public function __construct(
        private GitHub $gitHub,
        private CacheableRenderer $cacheableRenderer,
    ) {
    }

    #[Route(path: '/api/internal/latest-release', name: 'latest_release', methods: ['GET'])]
    public function handle(): JsonResponse
    {
        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'app.latest-release',
                cacheTags: CacheTags::empty(),
                ttlInSeconds: self::SIX_HOURS_IN_SECONDS,
            ),
            render: fn (): string => $this->renderFor(),
        );

        return new JsonResponse(
            $render->getContent() ?? '{}',
            headers: [
                ...$render->getCacheHeaders(),
                'Cache-Control' => sprintf('private, max-age=%d', $render->getTtlInSeconds() ?? self::SIX_HOURS_IN_SECONDS),
            ],
            json: true,
        );
    }

    private function renderFor(): string
    {
        try {
            $latestRelease = $this->gitHub->getLatestRelease();
        } catch (GuzzleException|CorruptedData) {
            return Json::encode(['latestVersion' => null, 'updateAvailable' => false]);
        }

        return Json::encode([
            'latestVersion' => $latestRelease,
            'updateAvailable' => $this->isUpdateAvailable($latestRelease),
        ]);
    }

    private function isUpdateAvailable(string $latestRelease): bool
    {
        if (!version_compare(ltrim($latestRelease, 'v'), ltrim(AppVersion::getSemanticVersion(), 'v'), '>')) {
            return false;
        }

        try {
            $workflowRuns = $this->gitHub->getWorkflowRuns([
                'event' => 'push',
                'status' => 'completed',
                'conclusion' => 'success',
                'exclude_pull_requests' => 'true',
                'branch' => $latestRelease,
            ]);
        } catch (GuzzleException|CorruptedData) {
            return false;
        }

        return array_any(
            $workflowRuns,
            fn (array $workflowRun): bool => '.github/workflows/docker-image.yml' === $workflowRun['path'],
        );
    }
}
