<?php

declare(strict_types=1);

namespace App\Application\Build\BuildRecordingDevices;

use App\Domain\Gear\Maintenance\Task\Progress\MaintenanceTaskProgressCalculator;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use League\Flysystem\FilesystemOperator;
use Twig\Environment;

final readonly class BuildRecordingDevicesCommandHandler implements CommandHandler
{
    public function __construct(
        private RecordingDeviceRepository $recordingDeviceRepository,
        private MaintenanceTaskProgressCalculator $maintenanceTaskProgressCalculator,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
        private FilesystemOperator $buildHtmlStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildRecordingDevices);

        $recordingDevices = $this->recordingDeviceRepository->findAll();
        $this->buildHtmlStorage->write(
            'gear/recording-devices.html',
            $this->twig->load('html/gear/recording-device/recording-devices.html.twig')->render([
                'maintenanceTaskIsDue' => !$this->maintenanceTaskProgressCalculator->getGearIdsThatHaveDueTasks()->isEmpty(),
                'devices' => $recordingDevices,
                'unitSystem' => $this->settingsRepository->appearance()->getUnitSystem(),
            ]),
        );

        $this->buildHtmlStorage->write(
            'gear/recording-devices/info.html',
            $this->twig->load('html/gear/recording-device/recording-device-info.html.twig')->render([
                'devices' => $recordingDevices,
            ]),
        );
    }
}
