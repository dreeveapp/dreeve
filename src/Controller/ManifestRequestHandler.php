<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\AppName;
use App\Application\AppUrl;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final readonly class ManifestRequestHandler
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private AppUrl $appUrl,
        private KernelProjectDir $kernelProjectDir,
    ) {
    }

    #[Route(path: '/manifest.json', name: 'manifest', methods: ['GET'], priority: 3)]
    public function handle(): Response
    {
        $athlete = $this->settingsRepository->general()->getAthlete();

        $manifest = file_get_contents($this->kernelProjectDir.'/templates/manifest.json');
        assert(is_string($manifest));
        $manifest = strtr($manifest, [
            '[APP_NAME]' => sprintf('%s | %s', AppName::LABEL, $athlete->getName()),
            '[APP_HOST]' => (string) $this->appUrl,
            '[APP_SHORT_NAME]' => AppName::LABEL,
            '[APP_BASE_PATH]' => $this->appUrl->getBasePath() ?? '',
        ]);

        $response = new Response($manifest);
        $response->headers->set('Content-Type', 'application/manifest+json');

        return $response;
    }
}
