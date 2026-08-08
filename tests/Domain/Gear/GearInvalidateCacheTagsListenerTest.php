<?php

namespace App\Tests\Domain\Gear;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\RenderCache;
use App\Infrastructure\Cache\RootCacheTag;
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

    public function testItDoesNotInvalidateWhenAnImportRewritesTheSameValues(): void
    {
        $this->gearRepository->add(GearBuilder::fromDefaults()->build());
        $this->warmUpRenderCache();

        $this->gearRepository->update(
            $this->gearRepository->find(GearId::fromUnprefixed('1'))
                ->withName('Existing gear')
                ->withIsRetired(false)
        );

        $this->assertTrue($this->isServedFromCache());
    }

    public function testItDoesNotInvalidateWhenGearIsRead(): void
    {
        $this->gearRepository->add(GearBuilder::fromDefaults()->build());
        $this->warmUpRenderCache();

        $this->gearRepository->findAll();

        $this->assertTrue($this->isServedFromCache());
    }

    private function isServedFromCache(): bool
    {
        return $this->renderCache->get(
            cacheKey: RootCacheTag::GEAR->value,
            cacheability: Cacheability::for('stub', CacheTags::of(RootCacheTag::GEAR)),
            callback: fn (): string => 'rendered',
        )->wasServedFromCache();
    }

    private function warmUpRenderCache(): void
    {
        $this->renderCache->get(
            cacheKey: RootCacheTag::GEAR->value,
            cacheability: Cacheability::for('stub', CacheTags::of(RootCacheTag::GEAR)),
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
