<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Activity\ActivityType;
use App\Domain\Settings\AthleteFtpSettings;
use PHPUnit\Framework\TestCase;

class AthleteFtpSettingsTest extends TestCase
{
    public function testItAppliesDefaultsForAnEmptyConfiguration(): void
    {
        $this->assertCount(0, AthleteFtpSettings::fromArray(null)->getHistory()->findAll(ActivityType::RIDE));
    }

    public function testItBuildsTheFtpHistoryFromStoredValues(): void
    {
        $history = AthleteFtpSettings::fromArray([
            'ftpHistory' => [
                'cycling' => [['on' => '2023-01-01', 'ftp' => 250]],
                'running' => [['on' => '2023-01-01', 'ftp' => 130]],
            ],
        ])->getHistory();

        $this->assertCount(1, $history->findAll(ActivityType::RIDE));
        $this->assertCount(1, $history->findAll(ActivityType::RUN));
    }
}
