<?php

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\GearMaintenanceConfig;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
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
                    gearId: GearId::fromUnprefixed('b1'),
                    from: SerializableDateTime::fromString('2024-11-01'),
                    until: SerializableDateTime::fromString('2025-03-15'),
                ),
                GearComponentInstallation::create(
                    gearId: GearId::fromUnprefixed('b1'),
                    from: SerializableDateTime::fromString('2025-11-01'),
                    until: null,
                ),
                GearComponentInstallation::create(
                    gearId: GearId::fromUnprefixed('b2'),
                    from: null,
                    until: SerializableDateTime::fromString('2024-11-01'),
                ),
            ]),
            $config->getGearComponents()->getFirst()->getInstallations()
        );
    }

    public function testFromArrayMapsLegacyAttachedToToOpenEndedInstallations(): void
    {
        $config = GearMaintenanceConfig::fromArray($this->getValidYml());

        $this->assertEquals(
            GearComponentInstallations::fromArray([
                GearComponentInstallation::create(gearId: GearId::fromUnprefixed('bike-one-gear-id'), from: null, until: null),
                GearComponentInstallation::create(gearId: GearId::fromUnprefixed('bike-two-gear-id'), from: null, until: null),
            ]),
            $config->getGearComponents()->getFirst()->getInstallations()
        );
    }

    public function testJsonSerializeWritesInstallations(): void
    {
        $config = $this->getConfigWithInstallations();

        $this->assertSame(
            $config['components'][0],
            Json::decode(Json::encode(GearMaintenanceConfig::fromArray($config)->getGearComponents()->getFirst()))
        );
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
