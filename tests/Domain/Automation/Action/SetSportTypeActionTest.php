<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Action;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Automation\Action\SetSportTypeAction;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class SetSportTypeActionTest extends TestCase
{
    private SetSportTypeAction $action;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['sportType' => ''],
            $this->action->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['sportType' => 'Ride']));
    }

    public function testGuardThrowsOnInvalidSportType(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('Invalid sport type "nope".'));

        $this->action->guardValidConfiguration(RuleConfiguration::fromConfig(['sportType' => 'nope']));
    }

    public function testApplyToSetsTheSportType(): void
    {
        $activity = ActivityBuilder::fromDefaults()->withSportType(SportType::RUN)->build();

        $activity = $this->action->applyTo($activity, RuleConfiguration::fromConfig(['sportType' => 'Ride']));

        $this->assertSame(SportType::RIDE, $activity->getSportType());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new SetSportTypeAction();
    }
}
