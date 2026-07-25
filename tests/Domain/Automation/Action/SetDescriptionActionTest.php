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
use PHPUnit\Framework\Attributes\TestWith;
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

    #[TestWith(data: [''])]
    #[TestWith(data: ['[activity:name] on [activity:start-date:d-m-Y]'])]
    #[TestWith(data: ['[5x400m] [note: hello] [foo:bar]'])]
    public function testGuardPassesForValidConfiguration(string $description): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['description' => $description]));
    }

    public function testGuardPassesWhenDescriptionIsNotAString(): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig([]));
        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['description' => 3]));
    }

    public function testGuardThrowsOnUnknownToken(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('Unknown token(s): [activity:pizza].'));

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['description' => 'Intervals: [activity:pizza]']));
    }

    #[TestWith(data: ['Felt great today', 'Felt great today'])]
    #[TestWith(data: ['Started on [activity:start-date]', 'Started on default-format'])]
    #[TestWith(data: ['Ridden with [gear:name]', 'Ridden with [gear:name]'])]
    public function testApplyToSetsTheDescriptionReplacingResolvableTokens(string $configuredDescription, string $expectedDescription): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $activity = $this->action->applyTo($activity, RuleConfiguration::fromConfig(['description' => $configuredDescription]));

        $this->assertSame($expectedDescription, $activity->getDescription());
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
