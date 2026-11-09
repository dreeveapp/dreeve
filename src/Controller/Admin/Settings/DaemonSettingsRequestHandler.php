<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Domain\Backup\AvailableBackups;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class DaemonSettingsRequestHandler
{
    public function __construct(
        private Environment $twig,
        #[Autowire(service: DbalSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
        private AvailableBackups $availableBackups,
    ) {
    }

    #[Route(path: '/admin/settings/daemon', name: 'admin_settings_daemon', methods: ['GET'], priority: 10)]
    public function handle(): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/settings/daemon.html.twig', [
            'dispatchCommand' => UpdateSettings::getCommandName(),
            'group' => SettingsGroup::DAEMON,
            'settings' => $this->settingsRepository->findGroup(SettingsGroup::DAEMON),
            'latestBackup' => $this->availableBackups->findLatest(),
        ]));
    }

    #[Route(path: '/admin/backups/latest/download', name: 'admin_download_latest_backup', methods: ['GET'], priority: 10)]
    public function downloadLatestBackup(): StreamedResponse
    {
        $latestBackup = $this->availableBackups->findLatest() ?? throw new NotFoundHttpException('There is no backup to download');

        return new StreamedResponse(
            function () use ($latestBackup): void {
                set_time_limit(0);
                $this->availableBackups->passThrough($latestBackup);
            },
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/zstd',
                'Content-Length' => (string) $latestBackup->getSizeInBytes(),
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    disposition: HeaderUtils::DISPOSITION_ATTACHMENT,
                    filename: $latestBackup->getFilename(),
                ),
            ],
        );
    }
}
