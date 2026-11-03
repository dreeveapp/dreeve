<?php

declare(strict_types=1);

namespace App\Tests\Domain\Zwift;

use App\Domain\Zwift\ZwiftLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ZwiftLevelTest extends TestCase
{
    #[DataProvider('provideValidLevels')]
    public function testLevel(int $level): void
    {
        $this->assertEquals($level, ZwiftLevel::fromInt($level)->getValue());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function provideValidLevels(): array
    {
        return [
            'min' => [1],
            'max progress' => [100],
            'above max progress' => [150],
        ];
    }

    #[DataProvider('provideProgressPercentages')]
    public function testGetProgress(int $level, float $expectedPercentage): void
    {
        $this->assertEquals(
            $expectedPercentage,
            ZwiftLevel::fromInt($level)->getProgressPercentage()
        );
    }

    /**
     * @return array<string, array{int, float}>
     */
    public static function provideProgressPercentages(): array
    {
        return [
            'level 1' => [1, 1.0],
            'level 80' => [80, 66.17],
            'level 99 rounds down to 96' => [99, 96],
            'level 100' => [100, 100],
            'level 101 caps at 100' => [101, 100],
            'level 150 caps at 100' => [150, 100],
        ];
    }

    public function testItShouldThrowWhenLevelTooLow(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('ZwiftLevel must be at least 1'));

        ZwiftLevel::fromInt(0);
    }
}
