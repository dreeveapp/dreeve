<?php

declare(strict_types=1);

namespace App\Controller\Page\Gear;

use App\Application\Navigation\NavigationSection;
use App\Application\PageRenderer;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class RecordingDevicesRequestHandler
{
    public function __construct(
        private RecordingDeviceRepository $recordingDeviceRepository,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
        private PageRenderer $pageRenderer,
    ) {
    }

    #[Route(path: '/gear/recording-devices', name: 'gear_recording_devices', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'gear.recording-devices',
                cacheTags: CacheTags::of(
                    RootCacheTag::RECORDING_DEVICES,
                    RootCacheTag::ACTIVITIES,
                ),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::GEAR,
        );
    }

    private function renderFor(): string
    {
        return $this->twig->load('html/gear/recording-device/recording-devices.html.twig')->render([
            'devices' => $this->recordingDeviceRepository->findAll(),
            'unitSystem' => $this->settingsRepository->appearance()->getUnitSystem(),
        ]);
    }
}
