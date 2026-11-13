<?php

declare(strict_types=1);

namespace App\Controller\Admin\Backup;

use App\Domain\Backup\AvailableBackups;
use App\Infrastructure\Config\ManagedMode;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class BackupsRequestHandler
{
    public function __construct(
        private Environment $twig,
        private AvailableBackups $availableBackups,
        private ManagedMode $managedMode,
    ) {
    }

    #[Route(path: '/admin/backups', name: 'admin_backups', methods: ['GET'], priority: 10)]
    public function handle(): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/backup/backups.html.twig', [
            'backups' => array_reverse($this->availableBackups->findAll()),
            'isOnMountedVolume' => $this->availableBackups->isOnMountedVolume(),
            'isManaged' => $this->managedMode->isEnabled(),
        ]));
    }

    #[Route(
        path: '/admin/backups/{filename}/download',
        name: 'admin_download_backup',
        requirements: ['filename' => 'dreeve-\d{4}-\d{2}-\d{2}-\d{6}\.tar\.zst'],
        methods: ['GET'],
        priority: 10,
    )]
    public function download(string $filename): StreamedResponse
    {
        $backup = $this->availableBackups->findByName($filename);

        return new StreamedResponse(
            function () use ($backup): void {
                set_time_limit(0);
                $this->availableBackups->passThrough($backup);
            },
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/zstd',
                'Content-Length' => (string) $backup->getSizeInBytes(),
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    disposition: HeaderUtils::DISPOSITION_ATTACHMENT,
                    filename: $backup->getFilename(),
                ),
            ],
        );
    }
}
