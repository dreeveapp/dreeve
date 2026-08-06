<?php

namespace App\Tests\Domain\Gear;

use App\Domain\Gear\GearRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\RenderCache;
use App\Tests\ContainerTestCase;

class GearInvalidateCacheTagsListenerTest extends ContainerTestCase
{
    private GearRepository $gearRepository;
    private RenderCache $renderCache;

    public function testItInvalidatesWhenGearIsAdded(): void
    {
        $this->warmUpRenderCache();

        $this->gearRepository->add(GearBuilder::fromDefaults()->buildAsNewlyCreated());

        $this->assertFalse($this->isServedFromCache());
    }

    public function testItDoesNotInvalidateWhenGearIsMerelyHydratedAndStored(): void
    {
        $this->warmUpRenderCache();

        $this->gearRepository->add(GearBuilder::fromDefaults()->build());

        $this->assertTrue($this->isServedFromCache());
    }

    public function testItInvalidatesWhenGearIsRenamed(): void
    {
        $this->gearRepository->add(GearBuilder::fromDefaults()->build());
        $this->warmUpRenderCache();

        $this->gearRepository->update(GearBuilder::fromDefaults()->build()->withName('Renamed gear'));

        $this->assertFalse($this->isServedFromCache());
    }

    public function testItInvalidatesWhenGearIsRetired(): void
    {
        $this->gearRepository->add(GearBuilder::fromDefaults()->build());
        $this->warmUpRenderCache();

        $this->gearRepository->update(GearBuilder::fromDefaults()->build()->withIsRetired(true));

        $this->assertFalse($this->isServedFromCache());
    }

    public function testItDoesNotInvalidateWhenGearIsStoredWithoutChanges(): void
    {
        $this->gearRepository->add(GearBuilder::fromDefaults()->build());
        $this->warmUpRenderCache();

        $this->gearRepository->update(GearBuilder::fromDefaults()->build());

        $this->assertTrue($this->isServedFromCache());
    }

    public function testItDoesNotInvalidateWhenGearIsRead(): void
    {
        $this->gearRepository->add(GearBuilder::fromDefaults()->build());
        $this->warmUpRenderCache();

        // Reading enriches the gear with its activity types, which must not record an event.
        $this->gearRepository->findAll();

        $this->assertTrue($this->isServedFromCache());
    }

    private function isServedFromCache(): bool
    {
        return $this->renderCache->get(
            cacheKey: CacheTag::GEAR->value,
            cacheability: Cacheability::for('stub', CacheTags::of(CacheTag::GEAR)),
            callback: fn (): string => 'rendered',
        )->wasServedFromCache();
    }

    private function warmUpRenderCache(): void
    {
        $this->renderCache->get(
            cacheKey: CacheTag::GEAR->value,
            cacheability: Cacheability::for('stub', CacheTags::of(CacheTag::GEAR)),
            callback: fn (): string => 'rendered',
        );
        $this->assertTrue($this->isServedFromCache());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->gearRepository = $this->getContainer()->get(GearRepository::class);
        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
    }
}
