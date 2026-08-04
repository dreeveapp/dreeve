<?php

declare(strict_types=1);

namespace App\Controller\Admin\Gear\RecordingDevice;

use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ManageRecordingDeviceOverviewRequestHandler
{
    public function __construct(
        private Environment $twig,
        private RecordingDeviceRepository $recordingDeviceRepository,
    ) {
    }

    #[Route(path: '/admin/gear/recording-devices', name: 'admin_manage_recording_devices_overview', methods: ['GET'], priority: 10)]
    public function handle(): HtmlResponse
    {
        return new HtmlResponse($this->twig->render('html/admin/page/gear/recording-device/manage-recording-devices-overview.html.twig', [
            'recordingDevices' => $this->recordingDeviceRepository->findAll(),
        ]));
    }
}
