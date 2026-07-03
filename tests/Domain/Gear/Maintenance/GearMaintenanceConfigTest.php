<?php

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\GearMaintenanceConfig;
use App\Tests\ContainerTestCase;
use Symfony\Component\Yaml\Yaml;

class GearMaintenanceConfigTest extends ContainerTestCase
{
    /**
     * @param array<string, mixed> $config
     */
    private function createConfig(array $config): GearMaintenanceConfig
    {
        return GearMaintenanceConfig::fromArray($config);
    }

    public function testFromArrayWhenEmpty(): void
    {
        $this->assertFalse(
            $this->createConfig([])->isFeatureEnabled(),
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
            $this->createConfig($yml)->getAllReferencedGearIds()
        );
    }

    public function testNormalizeGearIds(): void
    {
        $config = $this->createConfig($this->getYmlStringThatNeedsNormalization());
        $config->normalizeGearIds(GearIds::fromArray([GearId::fromUnprefixed('b123456')]));

        $this->assertEquals(
            GearId::fromUnprefixed('b123456'),
            $config->getGearComponents()->getAllReferencedGearIds()->getFirst()
        );
    }

    private static function getValidYml(): array
    {
        return Yaml::parse(<<<YML
enabled: true
components:
  - id: 'gearComponent-chain'
    label: 'Some cool chain'
    localImagePath: 'chain.png'
    attachedTo:
      - 'bike-one-gear-id'
      - 'bike-two-gear-id'
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
    attachedTo:
      - 'bike-one-gear-id'
      - 'bike-two-gear-id'
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
    attachedTo:
      - '123456'
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
    attachedTo:
      - '123456'
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
