<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Action;

use App\Domain\Automation\Action\MarkAsGroupActivityAction;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class MarkAsGroupActivityActionTest extends TestCase
{
    private MarkAsGroupActivityAction $action;

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

    public function testApplyToAlwaysMarksActivityAsGroupActivity(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withIsGroupActivity(false)->build();

        $this->assertTrue(
            $this->action->applyTo($activity, RuleConfiguration::empty())->isGroupActivity()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new MarkAsGroupActivityAction();
    }
}
