<?php

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Gear\Maintenance\DbalGearComponentRepository;
use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Infrastructure\Eventing\EventBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;

class DbalGearComponentRepositoryTest extends ContainerTestCase
{
    private GearComponentRepository $gearComponentRepository;

    public function testAddAndFind(): void
    {
        foreach ($this->getComponents() as $gearComponent) {
            $this->gearComponentRepository->add($gearComponent);
        }

        $this->assertEquals(
            $this->getComponents()[0],
            $this->gearComponentRepository->find(GearComponentId::fromString('gearComponent-winter-wheelset')),
        );
    }

    public function testFindAllIsOrderedByLabel(): void
    {
        foreach ($this->getComponents() as $gearComponent) {
            $this->gearComponentRepository->add($gearComponent);
        }

        $this->assertSame(
            ['Winter tyres', 'Winter wheelset'],
            $this->gearComponentRepository->findAll()->map(static fn (GearComponent $gearComponent): string => (string) $gearComponent->getLabel()),
        );
    }

    public function testItRoundTripsTheStoredShape(): void
    {
        foreach ($this->getComponents() as $gearComponent) {
            $this->gearComponentRepository->add($gearComponent);
        }

        $this->assertSame(
            $this->getComponentsAsArray()[0],
            Json::decode(Json::encode($this->gearComponentRepository->find(GearComponentId::fromString('gearComponent-winter-wheelset')))),
        );
        $this->assertSame(
            $this->getComponentsAsArray()[1],
            Json::decode(Json::encode($this->gearComponentRepository->find(GearComponentId::fromString('gearComponent-winter-tyres')))),
        );
    }

    public function testUpdate(): void
    {
        [$wheelset] = $this->getComponents();
        $this->gearComponentRepository->add($wheelset);

        $updated = GearComponent::fromArray([...$this->getComponentsAsArray()[0], 'label' => 'Spare wheelset', 'isRetired' => true]);
        $this->gearComponentRepository->update($updated);

        $this->assertEquals($updated, $this->gearComponentRepository->find($wheelset->getId()));
    }

    public function testDelete(): void
    {
        [$wheelset] = $this->getComponents();
        $this->gearComponentRepository->add($wheelset);

        $this->gearComponentRepository->delete($wheelset);

        $this->expectExceptionObject(new EntityNotFound('GearComponent "gearComponent-winter-wheelset" not found'));
        $this->gearComponentRepository->find($wheelset->getId());
    }

    /**
     * @return list<GearComponent>
     */
    private function getComponents(): array
    {
        return array_map(GearComponent::fromArray(...), $this->getComponentsAsArray());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getComponentsAsArray(): array
    {
        return Json::decode((string) file_get_contents(__DIR__.'/fixtures/config-with-installations.json'))['components'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->gearComponentRepository = new DbalGearComponentRepository(
            $this->getConnection(),
            $this->getContainer()->get(EventBus::class),
        );
    }
}
