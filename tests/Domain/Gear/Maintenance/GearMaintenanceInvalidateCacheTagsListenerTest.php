<?php

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIdRepository;
use App\Domain\Gear\Maintenance\DbalGearComponentRepository;
use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class GearMaintenanceInvalidateCacheTagsListenerTest extends ContainerTestCase
{
    private GearMaintenanceRepository $gearMaintenanceRepository;
    private GearMaintenanceLogRepository $gearMaintenanceLogRepository;
    private GearComponentRepository $gearComponentRepository;
    private RenderCache $renderCache;

    public function testItInvalidatesWhenTheFeatureIsToggled(): void
    {
        $this->warmUpRenderCache();

        $this->gearMaintenanceRepository->updateConfig(
            isFeatureEnabled: true,
            ignoreRetiredGear: false,
        );

        $this->assertFalse($this->isServedFromCache());
    }

    public function testItInvalidatesWhenAMaintenanceLogIsAdded(): void
    {
        $this->warmUpRenderCache();

        $this->gearMaintenanceLogRepository->add($this->chainLubed());

        $this->assertFalse($this->isServedFromCache());
    }

    public function testItInvalidatesWhenAMaintenanceLogIsDeleted(): void
    {
        $log = $this->chainLubed();
        $this->gearMaintenanceLogRepository->add($log);
        $this->warmUpRenderCache();

        $this->gearMaintenanceLogRepository->delete($log->getId());

        $this->assertFalse($this->isServedFromCache());
    }

    public function testItInvalidatesWhenAComponentIsSaved(): void
    {
        $this->warmUpRenderCache();

        $this->gearComponentRepository->add(GearComponent::create(
            id: GearComponentId::fromUnprefixed('chain'),
            label: Name::fromString('Chain'),
            installations: GearComponentInstallations::empty(),
            localImagePath: null,
            purchasePrice: null,
            isRetired: false,
            notes: null,
        ));

        $this->assertFalse($this->isServedFromCache());
    }

    public function testItInvalidatesWhenAComponentIsDeleted(): void
    {
        $this->warmUpRenderCache();

        $this->gearComponentRepository->delete(GearComponentId::fromUnprefixed('chain'));

        $this->assertFalse($this->isServedFromCache());
    }

    public function testItDoesNotInvalidateWhenTheConfigIsMerelyRead(): void
    {
        $this->warmUpRenderCache();

        $this->gearMaintenanceRepository->find();

        $this->assertTrue($this->isServedFromCache());
    }

    private function chainLubed(): GearMaintenanceLog
    {
        return GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('b1'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
    }

    private function isServedFromCache(): bool
    {
        return $this->renderCache->get(
            cacheKey: RootCacheTag::GEAR_MAINTENANCE->value,
            cacheability: Cacheability::for('stub', CacheTags::of(RootCacheTag::GEAR_MAINTENANCE)),
            callback: fn (): string => 'rendered',
        )->wasServedFromCache();
    }

    private function warmUpRenderCache(): void
    {
        $this->renderCache->get(
            cacheKey: RootCacheTag::GEAR_MAINTENANCE->value,
            cacheability: Cacheability::for('stub', CacheTags::of(RootCacheTag::GEAR_MAINTENANCE)),
            callback: fn (): string => 'rendered',
        );
        $this->assertTrue($this->isServedFromCache());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->gearMaintenanceRepository = $this->getContainer()->get(GearMaintenanceRepository::class);
        $this->gearMaintenanceLogRepository = $this->getContainer()->get(GearMaintenanceLogRepository::class);
        $this->gearComponentRepository = new DbalGearComponentRepository(
            $this->getConnection(),
            $this->getContainer()->get(GearIdRepository::class),
            $this->getContainer()->get(EventBus::class),
        );
        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
    }
}
