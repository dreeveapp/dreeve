<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Action;

use App\Domain\Automation\Action\SetDescriptionAction;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\Tokenizer\Tokenizer;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Infrastructure\Tokenizer\ActivityTokenProviderStub;
use App\Tests\Infrastructure\Tokenizer\GearTokenProviderStub;
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

    public function testGuardPassesForValidTokens(): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['description' => '[activity:name] on [activity:start-date:d-m-Y]']));
    }

    public function testGuardPassesForNonTokenShapedText(): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['description' => '[5x400m] [note: hello] [foo:bar]']));
    }

    public function testGuardThrowsOnUnknownToken(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('Unknown token(s): [activity:pizza].'));

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['description' => 'Intervals: [activity:pizza]']));
    }

    public function testApplyToSetsTheDescription(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $activity = $this->action->applyTo($activity, RuleConfiguration::fromConfig(['description' => 'Felt great today']));

        $this->assertSame('Felt great today', $activity->getDescription());
    }

    public function testApplyToReplacesTokens(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $activity = $this->action->applyTo($activity, RuleConfiguration::fromConfig(['description' => 'Started on [activity:start-date]']));

        $this->assertSame('Started on default-format', $activity->getDescription());
    }

    public function testApplyToLeavesUnresolvableTokenVerbatim(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $activity = $this->action->applyTo($activity, RuleConfiguration::fromConfig(['description' => 'Ridden with [gear:name]']));

        $this->assertSame('Ridden with [gear:name]', $activity->getDescription());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new SetDescriptionAction(
            new Tokenizer([new ActivityTokenProviderStub(), new GearTokenProviderStub()])
        );
    }
}
