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

    public function testDefaultConfigurationIsEmpty(): void
    {
        $this->assertSame(
            [],
            $this->action->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForAnyConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration(RuleConfiguration::empty());
    }

    public function testApplyToAlwaysMarksActivityAsCommute(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withIsCommute(false)->build();

        $this->assertTrue(
            $this->action->applyTo($activity, RuleConfiguration::empty())->isCommute()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new MarkAsCommuteAction();
    }
}
