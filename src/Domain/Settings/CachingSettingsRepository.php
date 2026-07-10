<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;

final class CachingSettingsRepository implements SettingsRepository, ResetInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $findCache = [];
    private ?GeneralSettings $general = null;
    private ?AppearanceSettings $appearanceSettings = null;
    private ?ImportSettings $importSettings = null;
    private ?MetricsSettings $metricsSettings = null;
    private ?ZwiftSettings $zwiftSettings = null;
    private ?IntegrationsSettings $integrationsSettings = null;
    private ?DaemonSettings $daemonSettings = null;

    public function __construct(
        #[Autowire(service: KeyValueBasedSettingsRepository::class)]
        private readonly SettingsRepository $settingsRepository,
    ) {
    }

    public function find(SettingsGroup $group): array
    {
        return $this->findCache[$group->value] ??= $this->settingsRepository->find($group);
    }

    public function save(SettingsGroup $group, array $data): void
    {
        $this->settingsRepository->save($group, $data);

        $this->reset();
    }

    public function reset(): void
    {
        $this->findCache = [];
        $this->general = null;
        $this->appearanceSettings = null;
        $this->importSettings = null;
        $this->metricsSettings = null;
        $this->zwiftSettings = null;
        $this->integrationsSettings = null;
        $this->daemonSettings = null;
    }

    public function general(): GeneralSettings
    {
        return $this->general ??= $this->settingsRepository->general();
    }

    public function appearance(): AppearanceSettings
    {
        return $this->appearanceSettings ??= $this->settingsRepository->appearance();
    }

    public function import(): ImportSettings
    {
        return $this->importSettings ??= $this->settingsRepository->import();
    }

    public function metrics(): MetricsSettings
    {
        return $this->metricsSettings ??= $this->settingsRepository->metrics();
    }

    public function zwift(): ZwiftSettings
    {
        return $this->zwiftSettings ??= $this->settingsRepository->zwift();
    }

    public function integrations(): IntegrationsSettings
    {
        return $this->integrationsSettings ??= $this->settingsRepository->integrations();
    }

    public function daemon(): DaemonSettings
    {
        return $this->daemonSettings ??= $this->settingsRepository->daemon();
    }
}
