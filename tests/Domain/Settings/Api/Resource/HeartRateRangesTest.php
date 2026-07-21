<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\Api\Resource;

use App\Domain\Settings\Api\Resource\HeartRateRanges;
use PHPUnit\Framework\TestCase;

class HeartRateRangesTest extends TestCase
{
    public function testItConvertsADateMapToEntries(): void
    {
        $this->assertSame(
            [['on' => '2020-01-01', 'bpm' => 198], ['on' => '2025-01-10', 'bpm' => 193]],
            HeartRateRanges::toEntries(['2020-01-01' => 198, '2025-01-10' => 193])
        );
    }

    public function testItCoercesNumericStrings(): void
    {
        $this->assertSame(
            [['on' => '2020-01-01', 'bpm' => 198]],
            HeartRateRanges::toEntries(['2020-01-01' => '198'])
        );
    }

    public function testItSkipsNonNumericValues(): void
    {
        // Defensive: settings written before validation existed, or hand-edited
        // in the database, should not break a read.
        $this->assertSame(
            [['on' => '2020-01-01', 'bpm' => 198]],
            HeartRateRanges::toEntries(['2020-01-01' => 198, '2021-01-01' => 'nonsense'])
        );
    }

    public function testItReturnsAnEmptyListForAnEmptyMap(): void
    {
        $this->assertSame([], HeartRateRanges::toEntries([]));
    }
}
