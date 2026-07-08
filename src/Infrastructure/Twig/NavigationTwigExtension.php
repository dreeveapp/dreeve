<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Domain\Settings\SettingsRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

final readonly class NavigationTwigExtension
{
    public function __construct(
        private RequestStack $requestStack,
        private SettingsRepository $settingsRepository,
    ) {
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
