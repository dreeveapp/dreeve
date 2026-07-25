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
use PHPUnit\Framework\Attributes\TestWith;
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

    #[TestWith(data: ['Morning Ride'])]
    #[TestWith(data: ['[activity:name] on [activity:start-date:d-m-Y]'])]
    #[TestWith(data: ['[5x400m] [note: hello] [foo:bar]'])]
    public function testGuardPassesForValidConfiguration(string $name): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['name' => $name]));
    }

    #[TestWith(data: ['   ', 'A "name" is required.'])]
    #[TestWith(data: ['[activity:pizza] with [gear:name:foo]', 'Unknown token(s): [activity:pizza], [gear:name:foo].'])]
    public function testGuardThrowsOnInvalidConfiguration(string $name, string $expectedMessage): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule($expectedMessage));

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['name' => $name]));
    }

    #[TestWith(data: ['Morning Ride', 'Morning Ride'])]
    #[TestWith(data: ['Commute: [activity:name]', 'Commute: Morning Ride'])]
    #[TestWith(data: ['Ridden with [gear:name]', 'Ridden with [gear:name]'])]
    public function testApplyToSetsTheNameReplacingResolvableTokens(string $configuredName, string $expectedName): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $activity = $this->action->applyTo($activity, RuleConfiguration::fromConfig(['name' => $configuredName]));

        $this->assertSame($expectedName, $activity->getName());
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
