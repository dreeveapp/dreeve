<?php

namespace App\Tests\Domain\Gear\Maintenance\Installation;

use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\Installation\UsageWindow;
use App\Domain\Gear\Maintenance\Installation\UsageWindows;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UsageWindowsTest extends TestCase
{
    /**
     * @param array<int, ?string> $froms
     */
    #[DataProvider('provideFroms')]
    public function testGetEarliestFrom(array $froms, ?string $expectedEarliestFrom): void
    {
        $usageWindows = UsageWindows::fromArray(array_map(
            static fn (?string $from): UsageWindow => UsageWindow::create(
                gearId: GearId::fromUnprefixed('test'),
                from: null === $from ? null : SerializableDateTime::fromString($from),
                until: null,
                sportTypes: SportTypes::empty(),
            ),
            $froms,
        ));

        $this->assertEquals(
            null === $expectedEarliestFrom ? null : SerializableDateTime::fromString($expectedEarliestFrom),
            $usageWindows->getEarliestFrom(),
        );
    }

    /**
     * @return iterable<string, array{array<int, ?string>, ?string}>
     */
    public static function provideFroms(): iterable
    {
        yield 'no windows' => [[], null];
        yield 'one dated window' => [['2025-03-01'], '2025-03-01'];
        yield 'several dated windows' => [['2025-03-01', '2024-11-01', '2025-06-01'], '2024-11-01'];
        yield 'one window is not dated' => [['2025-03-01', null], null];
    }
}
