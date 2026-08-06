<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\TimeOfDayCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => 'lt', 'time' => '08:30']));
    }

    #[DataProvider('provideInvalidConfigurations')]
    public function testGuardThrowsOnInvalidConfiguration(string $operator, string $time, string $expectedMessage): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule($expectedMessage));

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => $operator, 'time' => $time]));
    }

    public function testMatchesBeforeAConfiguredTime(): void
    {
        // Activity starts at 07:15.
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 07:15:00'))
            ->build();

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'lt', 'time' => '09:00'])));
        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'lt', 'time' => '06:00'])));
    }

    public function testMatchesAfterAConfiguredTime(): void
    {
        // Activity starts at 18:45.
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 18:45:00'))
            ->build();

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'gt', 'time' => '17:00'])));
        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'gt', 'time' => '20:00'])));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideInvalidConfigurations(): iterable
    {
        yield 'invalid operator' => ['nope', '08:30', 'Invalid time of day operator "nope".'];
        yield 'malformed time' => ['lt', '25:00', 'Invalid time "25:00", expected HH:MM.'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = new TimeOfDayCondition();
    }
}
