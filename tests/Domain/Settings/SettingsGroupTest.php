<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\SettingsGroup;
use PHPUnit\Framework\TestCase;

class SettingsGroupTest extends TestCase
{
    public function testTheSlugIsKebabCase(): void
    {
        $this->assertSame('general', SettingsGroup::GENERAL->getSlug());
        $this->assertSame('athlete-weight', SettingsGroup::ATHLETE_WEIGHT->getSlug());
        $this->assertSame(SettingsGroup::ATHLETE_WEIGHT, SettingsGroup::fromSlug('athlete-weight'));
        $this->assertNull(SettingsGroup::fromSlug('unknown'));
    }

    public function testEveryGroupWithASettingsPageRoundTripsItsSlugAndHasATemplate(): void
    {
        foreach (SettingsGroup::cases() as $group) {
            if (!$group->hasSettingsPage()) {
                continue;
            }

            $this->assertSame($group, SettingsGroup::fromSlug($group->getSlug()));
            $this->assertFileExists(sprintf('%s/templates/html/admin/page/settings/%s.html.twig', dirname(__DIR__, 3), $group->getSlug()));
        }
    }
}
