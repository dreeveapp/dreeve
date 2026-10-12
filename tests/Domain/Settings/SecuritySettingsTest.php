<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\SecuritySettings;
use PHPUnit\Framework\TestCase;

class SecuritySettingsTest extends TestCase
{
    public function testItAppliesDefaultsForAnEmptyConfiguration(): void
    {
        $settings = SecuritySettings::fromArray(null);

        $this->assertFalse($settings->requiresAuthentication());
        $this->assertFalse($settings->visitorsCanDownloadOriginalFiles());
    }

    public function testItCoercesStoredStringBooleans(): void
    {
        $settings = SecuritySettings::fromArray([
            'requiresAuthentication' => '1',
            'visitorsCanDownloadOriginalFiles' => '1',
        ]);

        $this->assertTrue($settings->requiresAuthentication());
        $this->assertTrue($settings->visitorsCanDownloadOriginalFiles());
    }
}
