<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Action;

use App\Domain\Automation\Action\CalculateKilojoulesAction;
use App\Domain\Automation\RuleConfiguration;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalculateKilojoulesActionTest extends TestCase
{
    private CalculateKilojoulesAction $action;

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

    #[DataProvider('provideActivities')]
    public function testApplyTo(
        ?int $kilojoules,
        ?int $averagePower,
        int $movingTimeInSeconds,
        ?int $expected,
    ): void {
        $activityBuilder = ActivityBuilder::fromDefaults()
            ->withKilojoules($kilojoules)
            ->withMovingTimeInSeconds($movingTimeInSeconds);

        if (null !== $averagePower) {
            $activityBuilder->withAveragePower($averagePower);
        }

        $this->assertSame(
            $expected,
            $this->action->applyTo($activityBuilder->build(), RuleConfiguration::empty())->getKilojoules()
        );
    }

    public static function provideActivities(): array
    {
        return [
            'calculates from average power and moving time' => [null, 169, 5420, 916],
            'rounds to the nearest kilojoule' => [null, 100, 1005, 101],
            'fills a zero value' => [0, 147, 2121, 312],
            'never overwrites a device reported value' => [834, 176, 9024, 834],
            'skips activities without power' => [null, null, 3600, null],
            'skips activities with zero power' => [null, 0, 3600, null],
            'skips activities without moving time' => [null, 169, 0, null],
        ];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new CalculateKilojoulesAction();
    }
}
