<?php

namespace App\Tests\Domain\Gear\RecordingDevice;

use App\Domain\Gear\RecordingDevice\RecordingDevice;
use App\Domain\Gear\RecordingDevice\RecordingDeviceRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Tests\ContainerTestCase;
use Money\Currency;
use Money\Money;

class RecordingDeviceInvalidateCacheTagsListenerTest extends ContainerTestCase
{
    private RecordingDeviceRepository $recordingDeviceRepository;
    private RenderCache $renderCache;

    public function testItInvalidatesWhenAPurchasePriceIsSet(): void
    {
        $this->warmUpRenderCache();

        $this->recordingDeviceRepository->save(RecordingDevice::create(
            name: 'Polar Vantage M',
            purchasePrice: new Money(30000, new Currency('EUR')),
        ));

        $this->assertFalse($this->isServedFromCache());
    }

    public function testItDoesNotInvalidateWhenARecordingDeviceIsMerelyHydrated(): void
    {
        $this->recordingDeviceRepository->save(RecordingDevice::create(
            name: 'Polar Vantage M',
            purchasePrice: null,
        ));
        $this->warmUpRenderCache();

        $this->recordingDeviceRepository->findAll();

        $this->assertTrue($this->isServedFromCache());
    }

    private function isServedFromCache(): bool
    {
        return $this->renderCache->get(
            cacheKey: RootCacheTag::RECORDING_DEVICES->value,
            cacheability: Cacheability::for('stub', CacheTags::of(RootCacheTag::RECORDING_DEVICES)),
            callback: fn (): string => 'rendered',
        )->wasServedFromCache();
    }

    private function warmUpRenderCache(): void
    {
        $this->renderCache->get(
            cacheKey: RootCacheTag::RECORDING_DEVICES->value,
            cacheability: Cacheability::for('stub', CacheTags::of(RootCacheTag::RECORDING_DEVICES)),
            callback: fn (): string => 'rendered',
        );
        $this->assertTrue($this->isServedFromCache());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->recordingDeviceRepository = $this->getContainer()->get(RecordingDeviceRepository::class);
        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
    }
}
