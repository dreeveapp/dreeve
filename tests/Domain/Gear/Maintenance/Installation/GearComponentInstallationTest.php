<?php

namespace App\Tests\Domain\Gear\Maintenance\Installation;

use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GearComponentInstallationTest extends TestCase
{
    #[DataProvider(methodName: 'provideIsActiveOn')]
    public function testIsActiveOn(?string $from, ?string $until, string $on, bool $expectedIsActive): void
    {
        $installation = GearComponentInstallation::create(
            gearId: GearId::fromUnprefixed('1'),
            from: $from ? SerializableDateTime::fromString($from) : null,
            until: $until ? SerializableDateTime::fromString($until) : null,
        );

        $this->assertSame($expectedIsActive, $installation->isActiveOn(SerializableDateTime::fromString($on)));
    }

    /**
     * @return iterable<string, array{?string, ?string, string, bool}>
     */
    public static function provideIsActiveOn(): iterable
    {
        yield 'open-ended' => [null, null, '2026-10-01 10:00:00', true];
        yield 'before from' => ['2026-10-01', null, '2026-09-30 23:59:59', false];
        yield 'on from' => ['2026-10-01', null, '2026-10-01 00:00:00', true];
        yield 'before until' => [null, '2026-10-01', '2026-09-30 23:59:59', true];
        yield 'on until' => [null, '2026-10-01', '2026-10-01 00:00:00', false];
        yield 'within window' => ['2026-01-01', '2026-03-01', '2026-02-01 08:00:00', true];
        yield 'after window' => ['2026-01-01', '2026-03-01', '2026-03-02 08:00:00', false];
    }

    #[DataProvider(methodName: 'provideInvalidWindows')]
    public function testItShouldThrowWhenUntilIsNotAfterFrom(string $from, string $until): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('An installation must end after it starts'));

        GearComponentInstallation::create(
            gearId: GearId::fromUnprefixed('1'),
            from: SerializableDateTime::fromString($from),
            until: SerializableDateTime::fromString($until),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidWindows(): iterable
    {
        yield 'until before from' => ['2026-10-01', '2026-09-01'];
        yield 'until equals from' => ['2026-10-01', '2026-10-01'];
    }
}
