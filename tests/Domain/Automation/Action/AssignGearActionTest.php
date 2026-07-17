<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Action;

use App\Domain\Automation\Action\AssignGearAction;
use App\Domain\Automation\InvalidAutomationRule;
use App\Domain\Automation\RuleConfiguration;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class AssignGearActionTest extends TestCase
{
    private GearRepository $gearRepository;
    private AssignGearAction $action;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['gearId' => ''],
            $this->action->getDefaultConfiguration()->toArray()
        );
    }

    public function testGuardPassesForValidConfiguration(): void
    {
        $this->expectNotToPerformAssertions();

        $this->action->guardValidConfiguration($this->config('gear-123'));
    }

    public function testGuardThrowsOnEmptyGearId(): void
    {
        $this->expectExceptionObject(new InvalidAutomationRule('A "gearId" is required.'));

        $this->action->guardValidConfiguration($this->config('   '));
    }

    public function testApplyToAssignsTheConfiguredGear(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $activity = $this->action->applyTo($activity, $this->config('gear-123'));

        $this->assertEquals(GearId::fromString('gear-123'), $activity->getGearId());
    }

    private function config(string $gearId): RuleConfiguration
    {
        return RuleConfiguration::fromConfig(['gearId' => $gearId]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->gearRepository = $this->createStub(GearRepository::class);
        $this->action = new AssignGearAction($this->gearRepository);
    }
}
