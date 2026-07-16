<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Action;

use App\Domain\Automation\Action\MarkAsCommuteAction;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class MarkAsCommuteActionTest extends TestCase
{
    private MarkAsCommuteAction $action;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['isCommute' => true],
            $this->action->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForAnyConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['isCommute' => false]));
    }

    public function testApplyToMarksActivityAsCommute(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $this->assertTrue(
            $this->action->applyTo($activity, RuleConfiguration::fromConfig(['isCommute' => true]))->isCommute()
        );
    }

    public function testApplyToUnmarksActivityAsCommute(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $this->assertFalse(
            $this->action->applyTo($activity, RuleConfiguration::fromConfig(['isCommute' => false]))->isCommute()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new MarkAsCommuteAction();
    }
}
