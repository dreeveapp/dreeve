<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\TimeOfDayCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class TimeOfDayConditionTest extends TestCase
{
    private TimeOfDayCondition $condition;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['operator' => 'lt', 'time' => ''],
            $this->condition->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->condition->guardValidConfiguration($this->config('lt', '08:30'));
    }

    public function testGuardThrowsOnInvalidOperator(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('Invalid time of day operator "nope".'));

        $this->condition->guardValidConfiguration($this->config('nope', '08:30'));
    }

    public function testGuardThrowsOnMalformedTime(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('Invalid time "25:00", expected HH:MM.'));

        $this->condition->guardValidConfiguration($this->config('lt', '25:00'));
    }

    public function testMatchesBeforeAConfiguredTime(): void
    {
        // Activity starts at 07:15.
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 07:15:00'))
            ->build();

        $this->assertTrue($this->condition->matches($activity, $this->config('lt', '09:00')));
        $this->assertFalse($this->condition->matches($activity, $this->config('lt', '06:00')));
    }

    public function testMatchesAfterAConfiguredTime(): void
    {
        // Activity starts at 18:45.
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 18:45:00'))
            ->build();

        $this->assertTrue($this->condition->matches($activity, $this->config('gt', '17:00')));
        $this->assertFalse($this->condition->matches($activity, $this->config('gt', '20:00')));
    }

    private function config(string $operator, string $time): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['operator' => $operator, 'time' => $time]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = new TimeOfDayCondition();
    }
}
