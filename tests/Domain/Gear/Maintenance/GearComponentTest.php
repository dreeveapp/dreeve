<?php

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearComponentWasUpdated;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Money\Money;
use PHPUnit\Framework\TestCase;

class GearComponentTest extends TestCase
{
    public function testFromArrayReadsInstallations(): void
    {
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
            GearComponent::fromArray($this->getComponents()[0])->getInstallations()
        );
    }

    public function testFromArrayReadsComponentParents(): void
    {
        $this->assertEquals(
            InstallationParent::component(GearComponentId::fromString('gearComponent-winter-wheelset')),
            GearComponent::fromArray($this->getComponents()[1])->getInstallations()->getFirst()->getParent()
        );
    }

    public function testFromArrayReadsIsRetiredAndNotes(): void
    {
        $wheelset = GearComponent::fromArray($this->getComponents()[0]);
        $tyres = GearComponent::fromArray($this->getComponents()[1]);

        $this->assertFalse($wheelset->isRetired());
        $this->assertSame("Serial WH-123\nTubeless ready", $wheelset->getNotes());
        $this->assertTrue($tyres->isRetired());
        $this->assertNull($tyres->getNotes());
    }

    public function testJsonSerializeWritesTheStoredShape(): void
    {
        foreach ($this->getComponents() as $component) {
            $this->assertSame($component, Json::decode(Json::encode(GearComponent::fromArray($component))));
        }
    }

    public function testFromArrayDoesNotRecordEvents(): void
    {
        $this->assertSame([], GearComponent::fromArray($this->getComponents()[0])->getRecordedEvents());
    }

    public function testWithersDoNotRecordEventsWhenNothingChanges(): void
    {
        $gearComponent = GearComponent::fromArray($this->getComponents()[0]);
        $unchanged = GearComponent::fromArray($this->getComponents()[0]);

        $this->assertSame([], $gearComponent
            ->withLabel($unchanged->getLabel())
            ->withInstallations($unchanged->getInstallations())
            ->withLocalImagePath($unchanged->getLocalImagePath())
            ->withPurchasePrice($unchanged->getPurchasePrice())
            ->withIsRetired($unchanged->isRetired())
            ->withNotes($unchanged->getNotes())
            ->withMaintenanceTasks($unchanged->getMaintenanceTasks())
            ->getRecordedEvents());
    }

    public function testWithersRecordAnEventForEveryChange(): void
    {
        $gearComponent = GearComponent::fromArray($this->getComponents()[0]);
        $other = GearComponent::fromArray($this->getComponents()[1]);

        $updated = $gearComponent
            ->withLabel($other->getLabel())
            ->withInstallations($other->getInstallations())
            ->withLocalImagePath('files/gear-maintenance/other.png')
            ->withPurchasePrice(Money::EUR(12345))
            ->withIsRetired($other->isRetired())
            ->withNotes('Other notes')
            ->withMaintenanceTasks($other->getMaintenanceTasks());

        $this->assertEquals($other->getLabel(), $updated->getLabel());
        $this->assertEquals($other->getInstallations(), $updated->getInstallations());
        $this->assertSame('files/gear-maintenance/other.png', $updated->getLocalImagePath());
        $this->assertEquals(Money::EUR(12345), $updated->getPurchasePrice());
        $this->assertTrue($updated->isRetired());
        $this->assertSame('Other notes', $updated->getNotes());
        $this->assertEquals($other->getMaintenanceTasks(), $updated->getMaintenanceTasks());
        $this->assertEquals(array_fill(0, 7, new GearComponentWasUpdated()), $updated->getRecordedEvents());
    }

    public function testNormalizeGearIds(): void
    {
        $gearComponent = GearComponent::fromArray([
            ...$this->getComponents()[0],
            'installations' => [['parent' => 'gear:123456', 'from' => null, 'until' => null, 'sportTypes' => []]],
        ]);
        $gearComponent->normalizeGearIds(GearIds::fromArray([GearId::fromUnprefixed('b123456')]));

        $this->assertEquals(
            InstallationParent::gear(GearId::fromUnprefixed('b123456')),
            $gearComponent->getInstallations()->getFirst()->getParent()
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getComponents(): array
    {
        return Json::decode((string) file_get_contents(__DIR__.'/fixtures/config-with-installations.json'))['components'];
    }
}
