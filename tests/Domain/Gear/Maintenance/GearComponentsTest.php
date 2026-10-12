<?php

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearComponents;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallationLoopDetected;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use App\Domain\Gear\Maintenance\Installation\UsageWindow;
use App\Domain\Gear\Maintenance\Installation\UsageWindows;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GearComponentsTest extends TestCase
{
    public function testResolveUsageWindowsFor(): void
    {
        $gearComponent = GearComponent::create(
            id: GearComponentId::fromUnprefixed('wheelset'),
            label: Name::fromString('Wheelset'),
            installations: GearComponentInstallations::fromArray([
                GearComponentInstallation::create(
                    parent: InstallationParent::gear(GearId::fromUnprefixed('b1')),
                    from: null,
                    until: SerializableDateTime::fromString('2025-03-15'),
                    sportTypes: SportTypes::fromArray([SportType::RIDE]),
                ),
                GearComponentInstallation::create(
                    parent: InstallationParent::gear(GearId::fromUnprefixed('b2')),
                    from: SerializableDateTime::fromString('2025-03-15'),
                    until: null,
                    sportTypes: SportTypes::empty(),
                ),
            ]),
            localImagePath: null,
            purchasePrice: null,
            isRetired: false,
            notes: null,
        );

        $this->assertEquals(
            UsageWindows::fromArray([
                UsageWindow::create(
                    gearId: GearId::fromUnprefixed('b1'),
                    from: null,
                    until: SerializableDateTime::fromString('2025-03-15'),
                    sportTypes: SportTypes::fromArray([SportType::RIDE]),
                ),
                UsageWindow::create(
                    gearId: GearId::fromUnprefixed('b2'),
                    from: SerializableDateTime::fromString('2025-03-15'),
                    until: null,
                    sportTypes: SportTypes::empty(),
                ),
            ]),
            GearComponents::fromArray([$gearComponent])->resolveUsageWindowsFor($gearComponent),
        );
    }

    public function testGetGearIdsCurrentlyUsing(): void
    {
        $gearComponent = GearComponent::create(
            id: GearComponentId::fromUnprefixed('wheelset'),
            label: Name::fromString('Wheelset'),
            installations: GearComponentInstallations::fromArray([
                GearComponentInstallation::create(
                    parent: InstallationParent::gear(GearId::fromUnprefixed('b1')),
                    from: null,
                    until: SerializableDateTime::fromString('2025-03-15'),
                    sportTypes: SportTypes::empty(),
                ),
                GearComponentInstallation::create(
                    parent: InstallationParent::gear(GearId::fromUnprefixed('b2')),
                    from: SerializableDateTime::fromString('2025-03-15'),
                    until: null,
                    sportTypes: SportTypes::empty(),
                ),
                GearComponentInstallation::create(
                    parent: InstallationParent::gear(GearId::fromUnprefixed('b3')),
                    from: SerializableDateTime::fromString('2025-07-01'),
                    until: null,
                    sportTypes: SportTypes::empty(),
                ),
                GearComponentInstallation::create(
                    parent: InstallationParent::gear(GearId::fromUnprefixed('b2')),
                    from: null,
                    until: null,
                    sportTypes: SportTypes::empty(),
                ),
            ]),
            localImagePath: null,
            purchasePrice: null,
            isRetired: false,
            notes: null,
        );

        $this->assertEquals(
            GearIds::fromArray([GearId::fromUnprefixed('b2')]),
            GearComponents::fromArray([$gearComponent])->getGearIdsCurrentlyUsing(
                $gearComponent,
                SerializableDateTime::fromString('2025-06-01 10:00:00'),
            ),
        );
    }

    /**
     * @param list<array{string, ?string, ?string, list<string>}> $expectedWindows
     */
    #[DataProvider('provideNestedInstallations')]
    public function testResolveUsageWindowsForNestedInstallations(string $gearComponentId, array $expectedWindows): void
    {
        $gearComponents = GearComponents::fromArray(array_map(
            GearComponent::fromArray(...),
            Json::decode((string) file_get_contents(__DIR__.'/fixtures/nested-installations.json'))['components'],
        ));
        $gearComponent = $gearComponents->find(
            static fn (GearComponent $gearComponent): bool => (string) $gearComponent->getId() === $gearComponentId
        );

        $this->assertEquals(
            UsageWindows::fromArray(array_map(
                static fn (array $window): UsageWindow => UsageWindow::create(
                    gearId: GearId::fromUnprefixed($window[0]),
                    from: $window[1] ? SerializableDateTime::fromString($window[1]) : null,
                    until: $window[2] ? SerializableDateTime::fromString($window[2]) : null,
                    sportTypes: SportTypes::fromArray(array_map(SportType::from(...), $window[3])),
                ),
                $expectedWindows,
            )),
            $gearComponents->resolveUsageWindowsFor($gearComponent),
        );
    }

    /**
     * @return iterable<string, array{string, list<array{string, ?string, ?string, list<string>}>}>
     */
    public static function provideNestedInstallations(): iterable
    {
        yield 'installed on gear' => ['gearComponent-wheelset', [['b1', null, '2025-03-15', ['Ride', 'GravelRide']]]];
        yield 'inherits the window of its parent' => ['gearComponent-tyres', [['b1', null, '2025-03-15', ['Ride', 'GravelRide']]]];
        yield 'intersects dates with its parent' => ['gearComponent-race-tyres', [['b1', '2025-02-01', '2025-03-15', ['Ride', 'GravelRide']]]];
        yield 'drops windows that do not overlap its parent' => ['gearComponent-spare-tyres', []];
        yield 'intersects sport types with its parent' => ['gearComponent-gravel-tyres', [['b1', null, '2025-03-15', ['GravelRide']]]];
        yield 'drops windows without sport types in common' => ['gearComponent-indoor-tyres', []];
        yield 'resolves multiple levels' => ['gearComponent-tube', [['b1', null, '2025-03-15', ['Ride', 'GravelRide']]]];
        yield 'follows a parent shared by multiple gears' => ['gearComponent-cassette', [['b1', null, null, ['VirtualRide']], ['b2', '2025-06-01', null, ['VirtualRide']]]];
        yield 'ignores a parent that does not exist' => ['gearComponent-orphan', []];
    }

    public function testResolveUsageWindowsForThrowsOnALoop(): void
    {
        $gearComponents = GearComponents::fromArray(array_map(
            GearComponent::fromArray(...),
            Json::decode((string) file_get_contents(__DIR__.'/fixtures/nested-installations.json'))['components'],
        ));

        $this->expectExceptionObject(new GearComponentInstallationLoopDetected('Component "loop-a" is installed on itself'));

        $gearComponents->resolveUsageWindowsFor($gearComponents->find(
            static fn (GearComponent $gearComponent): bool => 'gearComponent-loop-a' === (string) $gearComponent->getId()
        ));
    }

    public function testWithoutRetired(): void
    {
        $gearComponents = GearComponents::fromArray(array_map(
            GearComponent::fromArray(...),
            Json::decode((string) file_get_contents(__DIR__.'/fixtures/config-with-installations.json'))['components'],
        ));

        $this->assertSame(
            ['gearComponent-winter-wheelset'],
            $gearComponents->withoutRetired()->map(static fn (GearComponent $gearComponent): string => (string) $gearComponent->getId()),
        );
    }

    public function testGetAllReferencedGearIds(): void
    {
        $gearComponents = GearComponents::fromArray(array_map(
            GearComponent::fromArray(...),
            Json::decode((string) file_get_contents(__DIR__.'/fixtures/config-with-installations.json'))['components'],
        ));

        $this->assertEquals(
            GearIds::fromArray([GearId::fromUnprefixed('b1'), GearId::fromUnprefixed('b2')]),
            $gearComponents->getAllReferencedGearIds(),
        );
    }
}
