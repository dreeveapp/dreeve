<?php

declare(strict_types=1);

namespace App\Application\Build\BuildManifest;

use App\Application\AppName;
use App\Application\AppUrl;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use League\Flysystem\FilesystemOperator;

final readonly class BuildManifestCommandHandler implements CommandHandler
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private AppUrl $appUrl,
        private KernelProjectDir $kernelProjectDir,
        private FilesystemOperator $buildStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildManifest);

        $athlete = $this->settingsRepository->general()->getAthlete();

        $manifest = file_get_contents($this->kernelProjectDir.'/templates/manifest.json');
        assert(is_string($manifest));
        $manifest = strtr($manifest, [
            '[APP_NAME]' => sprintf('%s | %s', AppName::LABEL, $athlete->getName()),
            '[APP_HOST]' => (string) $this->appUrl,
            '[APP_SHORT_NAME]' => AppName::LABEL,
            '[APP_BASE_PATH]' => $this->appUrl->getBasePath() ?? '',
        ]);

        $this->buildStorage->write('manifest.json', $manifest);
    }
}
