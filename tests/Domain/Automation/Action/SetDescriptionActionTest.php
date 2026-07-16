<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Action;

use App\Domain\Automation\Action\SetDescriptionAction;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class SetDescriptionActionTest extends TestCase
{
    private SetDescriptionAction $action;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['description' => ''],
            $this->action->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForAnyConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['description' => '']));
    }

    public function testApplyToSetsTheDescription(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $activity = $this->action->applyTo($activity, RuleConfiguration::fromConfig(['description' => 'Felt great today']));

        $this->assertSame('Felt great today', $activity->getDescription());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new SetDescriptionAction();
    }
}
