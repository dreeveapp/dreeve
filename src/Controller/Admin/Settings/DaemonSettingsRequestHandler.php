<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Domain\Import\ImportMode;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Infrastructure\Config\ManagedMode;
use App\Infrastructure\Daemon\Cron\CronActionId;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class DaemonSettingsRequestHandler
{
    public function __construct(
        private Environment $twig,
        #[Autowire(service: DbalSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
        private ImportMode $importMode,
        private ManagedMode $managedMode,
    ) {
    }

    #[Route(path: '/admin/settings/daemon', name: 'admin_settings_daemon', methods: ['GET'], priority: 10)]
    public function handle(): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/settings/daemon.html.twig', [
            'dispatchCommand' => UpdateSettings::getCommandName(),
            'group' => SettingsGroup::DAEMON,
            'settings' => $this->settingsRepository->findGroup(SettingsGroup::DAEMON),
            'cronActions' => array_values(array_filter(
                CronActionId::cases(),
                fn (CronActionId $cronActionId): bool => $cronActionId->supports(
                    importMode: $this->importMode,
                    managedMode: $this->managedMode,
                ),
            )),
        ]));
    }
}
