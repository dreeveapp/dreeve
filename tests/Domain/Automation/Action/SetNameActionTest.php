<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Action;

use App\Domain\Automation\Action\SetNameAction;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\Tokenizer\Tokenizer;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Infrastructure\Tokenizer\ActivityTokenProviderStub;
use App\Tests\Infrastructure\Tokenizer\GearTokenProviderStub;
use PHPUnit\Framework\TestCase;

class SetNameActionTest extends TestCase
{
    private SetNameAction $action;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['name' => ''],
            $this->action->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['name' => 'Morning Ride']));
    }

    public function testGuardPassesForValidTokens(): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['name' => '[activity:name] on [activity:start-date:d-m-Y]']));
    }

    public function testGuardPassesForNonTokenShapedText(): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['name' => '[5x400m] [note: hello] [foo:bar]']));
    }

    public function testGuardThrowsOnEmptyName(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('A "name" is required.'));

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['name' => '   ']));
    }

    public function testGuardThrowsOnUnknownToken(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('Unknown token(s): [activity:pizza], [gear:name:foo].'));

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['name' => '[activity:pizza] with [gear:name:foo]']));
    }

    public function testApplyToSetsTheName(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $activity = $this->action->applyTo($activity, RuleConfiguration::fromConfig(['name' => 'Morning Ride']));

        $this->assertSame('Morning Ride', $activity->getName());
    }

    public function testApplyToReplacesTokens(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $activity = $this->action->applyTo($activity, RuleConfiguration::fromConfig(['name' => 'Commute: [activity:name]']));

        $this->assertSame('Commute: Morning Ride', $activity->getName());
    }

    public function testApplyToLeavesUnresolvableTokenVerbatim(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $activity = $this->action->applyTo($activity, RuleConfiguration::fromConfig(['name' => 'Ridden with [gear:name]']));

        $this->assertSame('Ridden with [gear:name]', $activity->getName());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new SetNameAction(
            new Tokenizer([new ActivityTokenProviderStub(), new GearTokenProviderStub()])
        );
    }
}
