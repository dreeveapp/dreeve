<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\ZwiftSettings;
use PHPUnit\Framework\TestCase;

class ZwiftSettingsTest extends TestCase
{
    public function testItAppliesDefaultsForAnEmptyConfiguration(): void
    {
        $settings = ZwiftSettings::fromArray([]);

        $this->assertNull($settings->getZwiftLevel());
        $this->assertNull($settings->getZwiftRacingScore());
    }

    public function testItBuildsFromStoredIntValues(): void
    {
        $settings = ZwiftSettings::fromArray([
            'level' => 80,
            'racingScore' => 495,
        ]);

        $this->assertSame(80, $settings->getZwiftLevel()?->getValue());
        $this->assertSame(495, $settings->getZwiftRacingScore()?->getValue());
    }

    public function testItBuildsFromStoredStringValues(): void
    {
        $settings = ZwiftSettings::fromArray([
            'level' => '100',
            'racingScore' => '511',
        ]);

        $this->assertSame(100, $settings->getZwiftLevel()?->getValue());
        $this->assertSame(511, $settings->getZwiftRacingScore()?->getValue());
    }

    public function testItTreatsEmptyStringsAsNull(): void
    {
        $settings = ZwiftSettings::fromArray([
            'level' => '',
            'racingScore' => '  ',
        ]);

        $this->assertNull($settings->getZwiftLevel());
        $this->assertNull($settings->getZwiftRacingScore());
    }

    public function testItThrowsForAnInvalidRacingScore(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('ZwiftRacingScore must be a number between 0 and 1000'));

        ZwiftSettings::fromArray(['racingScore' => 1001]);
    }
}
