<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Import\ImportMode;
use App\Domain\Segment\SegmentRepository;
use App\Domain\Settings\SettingsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

final readonly class NavigationTwigExtension
{
    public function __construct(
        private RequestStack $requestStack,
        private SettingsRepository $settingsRepository,
        private ImportMode $importMode,
        private ActivityIdRepository $activityIdRepository,
        private SegmentRepository $segmentRepository,
    ) {
    }

    #[AsTwigFunction('isSegmentManagementAvailable')]
    public function isSegmentManagementAvailable(): bool
    {
        if (!$this->importMode->isFiles()) {
            return false;
        }

        return $this->activityIdRepository->count() > 0 || $this->segmentRepository->count() > 0;
    }

    #[AsTwigFunction('isAIIntegrationWithUIEnabled')]
    public function isAIIntegrationWithUIEnabled(): bool
    {
        return $this->settingsRepository->integrations()->isAIIntegrationWithUIEnabled();
    }

    /**
     * @param array<string, bool> $rules
     */
    #[AsTwigFunction('isActiveNavItem')]
    public function isActiveNavItem(array $rules): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return false;
        }

        $currentPath = $request->getPathInfo();
        foreach ($rules as $path => $exact) {
            if ($currentPath === $path) {
                return true;
            }
            if (!$exact && str_starts_with($currentPath, rtrim($path, '/').'/')) {
                return true;
            }
        }

        return false;
    }
}
