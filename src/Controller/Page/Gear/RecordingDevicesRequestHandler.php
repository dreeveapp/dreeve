<?php

declare(strict_types=1);

namespace App\Controller\Page\Gear;

use App\Application\Navigation\NavigationSection;
use App\Application\PageMetadata\PageMetadata;
use App\Application\PageRenderer;
use App\Domain\Calendar\Months;
use App\Domain\Gear\RecordingDevice\FindRecordingDeviceUsagePerMonth\FindRecordingDeviceUsagePerMonth;
use App\Domain\Gear\RecordingDevice\RecordingDevice;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Time\Clock\Clock;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Twig\Environment;

#[AsController]
final readonly class RecordingDevicesRequestHandler
{
    public function __construct(
        private RecordingDeviceRepository $recordingDeviceRepository,
        private SettingsRepository $settingsRepository,
        private QueryBus $queryBus,
        private Clock $clock,
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
                ttlInSeconds: $this->clock->getCurrentDateTimeImmutable()->getSecondsUntilMidnight(),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::GEAR,
            metadata: new PageMetadata(
                title: new TranslatableMessage('Recording devices', domain: 'metadata'),
                description: new TranslatableMessage('Statistics per device used to record activities', domain: 'metadata'),
            ),
        );
    }

    private function renderFor(): string
    {
        $devices = $this->recordingDeviceRepository->findAll();
        $deviceUsage = $this->queryBus->ask(new FindRecordingDeviceUsagePerMonth());

        return $this->twig->load('html/gear/recording-device/recording-devices.html.twig')->render([
            'devices' => $devices->usort(
                fn (RecordingDevice $a, RecordingDevice $b): int => $deviceUsage->getFor($b->getName())->getLastUsedOn() <=> $deviceUsage->getFor($a->getName())->getLastUsedOn()
            ),
            'deviceUsage' => $deviceUsage,
            'totalMovingTime' => Seconds::from((int) $devices->sum(fn (RecordingDevice $device): int => $device->getTimeTracked()->toInt())),
            'lastTwelveMonths' => Months::lastXMonths(12, $this->clock->getCurrentDateTimeImmutable()),
            'unitSystem' => $this->settingsRepository->appearance()->getUnitSystem(),
        ]);
    }
}
