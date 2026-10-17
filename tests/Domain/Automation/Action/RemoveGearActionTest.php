<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Action;

use App\Domain\Automation\Action\RemoveGearAction;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Gear\GearId;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class RemoveGearActionTest extends TestCase
{
    public function testDefaultConfigurationIsEmpty(): void
    {
        $this->assertSame(
            [],
            new RemoveGearAction()->getDefaultConfiguration()->toArray()
        );
    }

    public function testApplyToRemovesGear(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('1'))->build();

        $this->assertNull(
            new RemoveGearAction()->applyTo($activity, RuleConfiguration::empty())->getGearId()
        );
    }
}
