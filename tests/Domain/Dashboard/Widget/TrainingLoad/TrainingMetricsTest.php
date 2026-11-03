<?php

declare(strict_types=1);

namespace App\Tests\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Dashboard\Widget\TrainingLoad\TrainingMetrics;
use PHPUnit\Framework\TestCase;

class TrainingMetricsTest extends TestCase
{
    public function testMetricsWhenEmpty(): void
    {
        $metrics = TrainingMetrics::create([]);

        $this->assertNull($metrics->getCurrentAtl());
        $this->assertNull($metrics->getCurrentCtl());
        $this->assertNull($metrics->getCurrentTsb());
        $this->assertNull($metrics->getWeeklyTrimp());
        $this->assertNull($metrics->getCurrentMonotony());
        $this->assertNull($metrics->getCurrentStrain());
        $this->assertNull($metrics->getCurrentAcRatio());
    }
}
