<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearIds;
use App\Domain\Gear\Maintenance\ParsesGearMaintenanceComponentPayload;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Domain\Gear\Maintenance\Task\MaintenanceTasks;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\ValueObject\String\Name;
use PHPUnit\Framework\TestCase;

class ParsesGearMaintenanceComponentPayloadTest extends TestCase
{
    private object $parser;

    public function testParseLabel(): void
    {
        $this->assertEquals(
            Name::fromString('Chain'),
            $this->parser->doParseLabel(['label' => '  Chain  ']),
        );
    }

    public function testParseLabelThrowsWhenMissing(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A non-empty "label" is required.'));

        $this->parser->doParseLabel([]);
    }

    public function testParseLabelThrowsWhenNotAString(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A non-empty "label" is required.'));

        $this->parser->doParseLabel(['label' => ['nope']]);
    }

    public function testParseLabelThrowsWhenEmpty(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A non-empty "label" is required.'));

        $this->parser->doParseLabel(['label' => '   ']);
    }

    public function testParseAttachedTo(): void
    {
        $this->assertEquals(
            GearIds::fromArray([GearId::fromUnprefixed('b1'), GearId::fromUnprefixed('g2')]),
            $this->parser->doParseAttachedTo(['attachedTo' => [' b1 ', 'g2']]),
        );
    }

    public function testParseAttachedToThrowsWhenMissing(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('An "attachedTo" array is required.'));

        $this->parser->doParseAttachedTo([]);
    }

    public function testParseAttachedToThrowsWhenNotAnArray(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('An "attachedTo" array is required.'));

        $this->parser->doParseAttachedTo(['attachedTo' => 'b1']);
    }

    public function testParseAttachedToThrowsWhenEntryNotAString(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Each "attachedTo" entry must be a non-empty string.'));

        $this->parser->doParseAttachedTo(['attachedTo' => [123]]);
    }

    public function testParseAttachedToThrowsWhenEntryEmpty(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Each "attachedTo" entry must be a non-empty string.'));

        $this->parser->doParseAttachedTo(['attachedTo' => ['  ']]);
    }

    public function testParseMaintenanceTasksGeneratingMissingIds(): void
    {
        $tasks = $this->parser->doParseMaintenanceTasks([
            'maintenanceTasks' => [
                ['label' => '  Lube  ', 'interval' => ['value' => '500', 'unit' => 'km']],
            ],
        ], generateMissingIds: true);

        $this->assertCount(1, $tasks);
        $task = $tasks->getFirst();
        $this->assertEquals(Name::fromString('Lube'), $task->getLabel());
        $this->assertSame(500, $task->getIntervalValue());
        $this->assertSame(IntervalUnit::EVERY_X_KILOMETERS_USED, $task->getIntervalUnit());
        $this->assertNotEmpty((string) $task->getId());
    }

    public function testParseMaintenanceTasksWithProvidedId(): void
    {
        $tasks = $this->parser->doParseMaintenanceTasks([
            'maintenanceTasks' => [
                ['id' => ' maintenanceTask-chain-lubed ', 'label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
            ],
        ], generateMissingIds: false);

        $this->assertEquals(
            MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            $tasks->getFirst()->getId(),
        );
    }

    public function testParseMaintenanceTasksThrowsWhenMissing(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('At least one maintenance task is required.'));

        $this->parser->doParseMaintenanceTasks([], generateMissingIds: true);
    }

    public function testParseMaintenanceTasksThrowsWhenEmpty(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('At least one maintenance task is required.'));

        $this->parser->doParseMaintenanceTasks(['maintenanceTasks' => []], generateMissingIds: true);
    }

    public function testParseMaintenanceTasksThrowsWhenTaskNotAnObject(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Each maintenance task must be an object.'));

        $this->parser->doParseMaintenanceTasks(['maintenanceTasks' => ['nope']], generateMissingIds: true);
    }

    public function testParseMaintenanceTasksThrowsOnMissingLabel(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('A non-empty "label" is required for each maintenance task.'));

        $this->parser->doParseMaintenanceTasks([
            'maintenanceTasks' => [
                ['interval' => ['value' => 500, 'unit' => 'km']],
            ],
        ], generateMissingIds: true);
    }

    public function testParseMaintenanceTasksThrowsOnMissingInterval(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Each maintenance task requires an "interval" with "value" and "unit".'));

        $this->parser->doParseMaintenanceTasks([
            'maintenanceTasks' => [
                ['label' => 'Lube'],
            ],
        ], generateMissingIds: true);
    }

    public function testParseMaintenanceTasksThrowsOnNonNumericIntervalValue(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Each maintenance task requires an "interval" with "value" and "unit".'));

        $this->parser->doParseMaintenanceTasks([
            'maintenanceTasks' => [
                ['label' => 'Lube', 'interval' => ['value' => 'soon', 'unit' => 'km']],
            ],
        ], generateMissingIds: true);
    }

    public function testParseMaintenanceTasksThrowsOnInvalidIntervalUnit(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Invalid interval unit "lightyears".'));

        $this->parser->doParseMaintenanceTasks([
            'maintenanceTasks' => [
                ['label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'lightyears']],
            ],
        ], generateMissingIds: true);
    }

    public function testParseMaintenanceTasksThrowsWhenIdRequiredButMissing(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('An "id" is required for each maintenance task.'));

        $this->parser->doParseMaintenanceTasks([
            'maintenanceTasks' => [
                ['label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
            ],
        ], generateMissingIds: false);
    }

    public function testParseMaintenanceTasksThrowsOnDuplicateIds(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Duplicate maintenance task ids found.'));

        $this->parser->doParseMaintenanceTasks([
            'maintenanceTasks' => [
                ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
                ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Replace', 'interval' => ['value' => 1000, 'unit' => 'km']],
            ],
        ], generateMissingIds: false);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new class {
            use ParsesGearMaintenanceComponentPayload;

            public function doParseLabel(array $payload): Name
            {
                return self::parseLabel($payload);
            }

            public function doParseAttachedTo(array $payload): GearIds
            {
                return self::parseAttachedTo($payload);
            }

            public function doParseMaintenanceTasks(array $payload, bool $generateMissingIds): MaintenanceTasks
            {
                return self::parseMaintenanceTasks($payload, $generateMissingIds);
            }
        };
    }
}
