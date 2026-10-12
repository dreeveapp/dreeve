<?php

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearMaintenanceConfig;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use App\Domain\Gear\Maintenance\StartingValue;
use App\Infrastructure\Measurement\Length\Meter;
use App\Infrastructure\Measurement\Time\Seconds;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use Symfony\Component\Yaml\Yaml;

class GearMaintenanceConfigTest extends ContainerTestCase
{
    public function testFromArrayWhenEmpty(): void
    {
        $this->assertFalse(
            GearMaintenanceConfig::fromArray([])->isFeatureEnabled(),
        );
    }

    public function testGetAllReferencedGearIds(): void
    {
        $yml = $this->getValidYml();

        $this->assertEquals(
            GearIds::fromArray([
                GearId::fromUnprefixed('bike-one-gear-id'),
                GearId::fromUnprefixed('bike-two-gear-id'),
            ]),
            GearMaintenanceConfig::fromArray($yml)->getAllReferencedGearIds()
        );
    }

    public function testFromArrayReadsInstallations(): void
    {
        $config = GearMaintenanceConfig::fromArray($this->getConfigWithInstallations());

        $this->assertEquals(
            GearComponentInstallations::fromArray([
                GearComponentInstallation::create(
                    parent: InstallationParent::gear(GearId::fromUnprefixed('b1')),
                    from: SerializableDateTime::fromString('2024-11-01'),
                    until: SerializableDateTime::fromString('2025-03-15'),
                    sportTypes: SportTypes::fromArray([SportType::RIDE, SportType::GRAVEL_RIDE]),
                ),
                GearComponentInstallation::create(
                    parent: InstallationParent::gear(GearId::fromUnprefixed('b1')),
                    from: SerializableDateTime::fromString('2025-11-01'),
                    until: null,
                    sportTypes: SportTypes::empty(),
                ),
                GearComponentInstallation::create(
                    parent: InstallationParent::gear(GearId::fromUnprefixed('b2')),
                    from: null,
                    until: SerializableDateTime::fromString('2024-11-01'),
                    sportTypes: SportTypes::fromArray([SportType::VIRTUAL_RIDE]),
                ),
            ]),
            $config->getGearComponents()->getFirst()->getInstallations()
        );
    }

    public function testFromArrayReadsIsRetired(): void
    {
        $gearComponents = GearMaintenanceConfig::fromArray($this->getConfigWithInstallations())->getGearComponents();

        $this->assertFalse($gearComponents->getFirst()->isRetired());
        $this->assertTrue($gearComponents->getLast()->isRetired());
    }

    public function testFromArrayReadsNotes(): void
    {
        $gearComponents = GearMaintenanceConfig::fromArray($this->getConfigWithInstallations())->getGearComponents();

        $this->assertSame("Serial WH-123\nTubeless ready", $gearComponents->getFirst()->getNotes());
        $this->assertNull($gearComponents->getLast()->getNotes());
    }

    public function testFromArrayReadsStartingValues(): void
    {
        $gearComponents = GearMaintenanceConfig::fromArray($this->getConfigWithInstallations())->getGearComponents();

        $this->assertEquals(
            StartingValue::create(distance: Meter::from(1200000), movingTime: Seconds::from(180000)),
            $gearComponents->getFirst()->getStartingValue()
        );
        $this->assertNull($gearComponents->getLast()->getStartingValue());
    }

    public function testFromArrayReadsComponentParents(): void
    {
        $config = GearMaintenanceConfig::fromArray($this->getConfigWithInstallations());

        $this->assertEquals(
            InstallationParent::component(GearComponentId::fromString('gearComponent-winter-wheelset')),
            $config->getGearComponents()->getLast()->getInstallations()->getFirst()->getParent()
        );
    }

    public function testJsonSerializeWritesInstallations(): void
    {
        $config = $this->getConfigWithInstallations();
        $gearComponents = GearMaintenanceConfig::fromArray($config)->getGearComponents();

        $this->assertSame($config['components'][0], Json::decode(Json::encode($gearComponents->getFirst())));
        $this->assertSame($config['components'][1], Json::decode(Json::encode($gearComponents->getLast())));
    }

    public function testNormalizeGearIds(): void
    {
        $config = GearMaintenanceConfig::fromArray($this->getYmlStringThatNeedsNormalization());
        $config->normalizeGearIds(GearIds::fromArray([GearId::fromUnprefixed('b123456')]));

        $this->assertEquals(
            GearId::fromUnprefixed('b123456'),
            $config->getGearComponents()->getAllReferencedGearIds()->getFirst()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfigWithInstallations(): array
    {
        return Json::decode((string) file_get_contents(__DIR__.'/fixtures/config-with-installations.json'));
    }

    private static function getValidYml(): array
    {
        return Yaml::parse(<<<YML
enabled: true
components:
  - id: 'gearComponent-chain'
    label: 'Some cool chain'
    localImagePath: 'chain.png'
    isRetired: false
    notes: null
    installations:
      - { parent: 'gear:bike-one-gear-id', from: null, until: null, sportTypes: [] }
      - { parent: 'gear:bike-two-gear-id', from: null, until: null, sportTypes: [] }
    maintenance:
      - id: maintenanceTask-chain-lubed
        label: Lube
        interval:
          value: 500
          unit: km
      - label: Clean
        id: maintenanceTask-chain-cleaned
        interval:
          value: 200
          unit: hours
      - label: Replace
        id: maintenanceTask-chain-replaced
        interval:
          value: 500
          unit: days
  - id: 'gearComponent-chain-two'
    label: 'Some cool chain'
    localImagePath: 'chain.png'
    isRetired: false
    notes: null
    installations:
      - { parent: 'gear:bike-one-gear-id', from: null, until: null, sportTypes: [] }
      - { parent: 'gear:bike-two-gear-id', from: null, until: null, sportTypes: [] }
    maintenance:
      - id: maintenanceTask-chain-two-lubed
        label: Lube
        interval:
          value: 500
          unit: km
YML
        );
    }

    private function getYmlStringThatNeedsNormalization(): array
    {
        return Yaml::parse(<<<YML
enabled: true
components:
  - id: 'gearComponent-chain'
    label: 'Some cool chain'
    localImagePath: 'chain.png'
    isRetired: false
    notes: null
    installations:
      - { parent: 'gear:123456', from: null, until: null, sportTypes: [] }
    maintenance:
      - id: maintenanceTask-chain-lubed
        label: Lube
        interval:
          value: 500
          unit: km
      - label: Clean
        id: maintenanceTask-chain-cleaned
        interval:
          value: 200
          unit: hours
      - label: Replace
        id: maintenanceTask-chain-replaced
        interval:
          value: 500
          unit: days
  - id: 'gearComponent-chain-two'
    label: 'Some cool chain'
    localImagePath: 'chain.png'
    isRetired: false
    notes: null
    installations:
      - { parent: 'gear:123456', from: null, until: null, sportTypes: [] }
    maintenance:
      - id: maintenanceTask-chain-two-lubed
        label: Lube
        interval:
          value: 500
          unit: km
YML
        );
    }
}
