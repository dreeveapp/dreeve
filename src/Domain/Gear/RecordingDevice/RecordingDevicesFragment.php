<?php

declare(strict_types=1);

namespace App\Domain\Gear\RecordingDevice;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use Twig\Environment;

final readonly class RecordingDevicesFragment implements Fragment
{
    public function __construct(
        private RecordingDeviceRepository $recordingDeviceRepository,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'gear/recording-devices';
    }

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: 'gear.recording-devices',
            cacheTags: CacheTags::of(
                RootCacheTag::RECORDING_DEVICES,
                RootCacheTag::ACTIVITIES,
            ),
            cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
        );
    }

    public function render(): string
    {
        return $this->twig->load('html/gear/recording-device/recording-devices.html.twig')->render([
            'devices' => $this->recordingDeviceRepository->findAll(),
            'unitSystem' => $this->settingsRepository->appearance()->getUnitSystem(),
        ]);
    }
}
