<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\StartDateCondition;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StartDateConditionTest extends TestCase
{
    private StartDateCondition $condition;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['operator' => 'lt', 'date' => ''],
            $this->condition->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => 'lt', 'date' => '2023-10-10']));
    }

    #[DataProvider('provideInvalidConfigurations')]
    public function testGuardThrowsOnInvalidConfiguration(string $operator, string $date, string $expectedMessage): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule($expectedMessage));

        $this->condition->guardValidConfiguration(RuleConfiguration::fromConfig(['operator' => $operator, 'date' => $date]));
    }

    public function testMatchesBeforeAConfiguredDate(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 07:15:00'))
            ->build();

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'lt', 'date' => '2024-01-01'])));
        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'lt', 'date' => '2023-01-01'])));
    }

    public function testMatchesAfterAConfiguredDate(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 18:45:00'))
            ->build();

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'gt', 'date' => '2023-01-01'])));
        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'gt', 'date' => '2024-01-01'])));
    }

    public function testMatchesOnTheConfiguredDate(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-10-10 07:15:00'))
            ->build();

        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'eq', 'date' => '2023-10-10'])));
        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'eq', 'date' => '2023-10-11'])));
    }

    #[DataProvider('provideTimesOfDay')]
    public function testTheTimeOfDayDoesNotAffectTheComparison(string $startDateTime): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString($startDateTime))
            ->build();

        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'lt', 'date' => '2023-10-10'])));
        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'lte', 'date' => '2023-10-10'])));
        $this->assertFalse($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'gt', 'date' => '2023-10-10'])));
        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'gte', 'date' => '2023-10-10'])));
        $this->assertTrue($this->condition->matches($activity, RuleConfiguration::fromConfig(['operator' => 'eq', 'date' => '2023-10-10'])));
    }

    public function testMatchesAcrossAYearBoundary(): void
    {
        $newYearsDay = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2024-01-01 00:30:00'))
            ->build();
        $newYearsEve = ActivityBuilder::fromDefaults()
            ->withStartDateTime(SerializableDateTime::fromString('2023-12-31 23:30:00'))
            ->build();

        $this->assertTrue($this->condition->matches($newYearsDay, RuleConfiguration::fromConfig(['operator' => 'gt', 'date' => '2023-12-31'])));
        $this->assertTrue($this->condition->matches($newYearsEve, RuleConfiguration::fromConfig(['operator' => 'lt', 'date' => '2024-01-01'])));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideInvalidConfigurations(): iterable
    {
        yield 'invalid operator' => ['nope', '2023-10-10', 'Invalid start date operator "nope".'];
        yield 'empty date' => ['lt', '', 'Invalid date "", expected YYYY-MM-DD.'];
        yield 'wrong format' => ['lt', '10-10-2023', 'Invalid date "10-10-2023", expected YYYY-MM-DD.'];
        yield 'impossible date' => ['lt', '2023-02-31', 'Invalid date "2023-02-31", expected YYYY-MM-DD.'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTimesOfDay(): iterable
    {
        yield 'midnight' => ['2023-10-10 00:00:00'];
        yield 'midday' => ['2023-10-10 12:00:00'];
        yield 'just before midnight' => ['2023-10-10 23:59:59'];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = new StartDateCondition();
    }
}
