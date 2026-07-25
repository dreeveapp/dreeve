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
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidLabelPayloads')]
    public function testParseLabelThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        $this->parser->doParseLabel($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidLabelPayloads(): iterable
    {
        yield 'label is missing' => [
            [],
            'A non-empty "label" is required.',
        ];

        yield 'label is not a string' => [
            ['label' => ['nope']],
            'A non-empty "label" is required.',
        ];

        yield 'label is empty' => [
            ['label' => '   '],
            'A non-empty "label" is required.',
        ];
    }

    public function testParseAttachedTo(): void
    {
        $this->assertEquals(
            GearIds::fromArray([GearId::fromUnprefixed('b1'), GearId::fromUnprefixed('g2')]),
            $this->parser->doParseAttachedTo(['attachedTo' => [' b1 ', 'g2']]),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidAttachedToPayloads')]
    public function testParseAttachedToThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        $this->parser->doParseAttachedTo($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidAttachedToPayloads(): iterable
    {
        yield 'attachedTo is missing' => [
            [],
            'An "attachedTo" array is required.',
        ];

        yield 'attachedTo is not an array' => [
            ['attachedTo' => 'b1'],
            'An "attachedTo" array is required.',
        ];

        yield 'attachedTo entry is not a string' => [
            ['attachedTo' => [123]],
            'Each "attachedTo" entry must be a non-empty string.',
        ];

        yield 'attachedTo entry is empty' => [
            ['attachedTo' => ['  ']],
            'Each "attachedTo" entry must be a non-empty string.',
        ];
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

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidMaintenanceTaskPayloads')]
    public function testParseMaintenanceTasksThrowsOnInvalidPayload(array $payload, bool $generateMissingIds, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        $this->parser->doParseMaintenanceTasks($payload, generateMissingIds: $generateMissingIds);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool, string}>
     */
    public static function provideInvalidMaintenanceTaskPayloads(): iterable
    {
        yield 'maintenanceTasks is missing' => [
            [],
            true,
            'At least one maintenance task is required.',
        ];

        yield 'maintenanceTasks is empty' => [
            ['maintenanceTasks' => []],
            true,
            'At least one maintenance task is required.',
        ];

        yield 'task is not an object' => [
            ['maintenanceTasks' => ['nope']],
            true,
            'Each maintenance task must be an object.',
        ];

        yield 'task label is missing' => [
            [
                'maintenanceTasks' => [
                    ['interval' => ['value' => 500, 'unit' => 'km']],
                ],
            ],
            true,
            'A non-empty "label" is required for each maintenance task.',
        ];

        yield 'task interval is missing' => [
            [
                'maintenanceTasks' => [
                    ['label' => 'Lube'],
                ],
            ],
            true,
            'Each maintenance task requires an "interval" with "value" and "unit".',
        ];

        yield 'task interval value is not numeric' => [
            [
                'maintenanceTasks' => [
                    ['label' => 'Lube', 'interval' => ['value' => 'soon', 'unit' => 'km']],
                ],
            ],
            true,
            'Each maintenance task requires an "interval" with "value" and "unit".',
        ];

        yield 'task interval unit is invalid' => [
            [
                'maintenanceTasks' => [
                    ['label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'lightyears']],
                ],
            ],
            true,
            'Invalid interval unit "lightyears".',
        ];

        yield 'task id is required but missing' => [
            [
                'maintenanceTasks' => [
                    ['label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
                ],
            ],
            false,
            'An "id" is required for each maintenance task.',
        ];

        yield 'task ids are duplicated' => [
            [
                'maintenanceTasks' => [
                    ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Lube', 'interval' => ['value' => 500, 'unit' => 'km']],
                    ['id' => 'maintenanceTask-chain-lubed', 'label' => 'Replace', 'interval' => ['value' => 1000, 'unit' => 'km']],
                ],
            ],
            false,
            'Duplicate maintenance task ids found.',
        ];
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
