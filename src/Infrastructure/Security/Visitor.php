<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Config\DemoMode;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class Visitor
{
    public function __construct(
        private Security $security,
        private DemoMode $demoMode,
        private SettingsRepository $settingsRepository,
    ) {
    }

    public function isAuthenticated(): bool
    {
        return $this->security->getUser() instanceof UserInterface;
    }

    public function isTrusted(): bool
    {
        return !$this->demoMode->isEnabled() || $this->isAuthenticated();
    }

    public function canDownloadOriginalFiles(): bool
    {
        return $this->isAuthenticated() || $this->settingsRepository->security()->visitorsCanDownloadOriginalFiles();
    }
}
