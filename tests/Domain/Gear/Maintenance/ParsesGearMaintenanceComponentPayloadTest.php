<?php

declare(strict_types=1);

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallations;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use App\Domain\Gear\Maintenance\ParsesGearMaintenanceComponentPayload;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Domain\Gear\Maintenance\Task\MaintenanceTasks;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
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

    #[DataProvider('provideNotes')]
    public function testParseNotes(array $payload, ?string $expectedNotes): void
    {
        $this->assertSame($expectedNotes, $this->parser->doParseNotes($payload));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ?string}>
     */
    public static function provideNotes(): iterable
    {
        yield 'missing' => [[], null];
        yield 'empty' => [['notes' => "  \n "], null];
        yield 'trimmed' => [['notes' => "  Serial WH-123\nTubeless  "], "Serial WH-123\nTubeless"];
    }

    public function testParseNotesThrowsWhenNotAString(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('"notes" must be a string.'));

        $this->parser->doParseNotes(['notes' => ['Serial']]);
    }

    public function testParseInstallations(): void
    {
        $this->assertEquals(
            GearComponentInstallations::fromArray([
                GearComponentInstallation::create(
                    parent: InstallationParent::gear(GearId::fromUnprefixed('b1')),
                    from: null,
                    until: SerializableDateTime::fromString('2025-03-15'),
                    sportTypes: SportTypes::fromArray([SportType::RIDE, SportType::GRAVEL_RIDE]),
                ),
                GearComponentInstallation::create(
                    parent: InstallationParent::gear(GearId::fromUnprefixed('g2')),
                    from: SerializableDateTime::fromString('2025-03-15'),
                    until: null,
                    sportTypes: SportTypes::empty(),
                ),
            ]),
            $this->parser->doParseInstallations(['installations' => [
                ['parent' => 'gear:b1', 'from' => '', 'until' => '2025-03-15', 'sportTypes' => ['Ride', 'GravelRide']],
                ['parent' => 'gear:g2', 'from' => '2025-03-15'],
            ]]),
        );
    }

    public function testParseInstallationsWhenThereAreNone(): void
    {
        $this->assertEquals(
            GearComponentInstallations::empty(),
            $this->parser->doParseInstallations([]),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidInstallationPayloads')]
    public function testParseInstallationsThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        $this->parser->doParseInstallations($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidInstallationPayloads(): iterable
    {
        yield 'installations is not an array' => [
            ['installations' => 'b1'],
            '"installations" must be an array.',
        ];

        yield 'installation is not an object' => [
            ['installations' => ['b1']],
            'Each installation must be an object.',
        ];

        yield 'parent is missing' => [
            ['installations' => [['from' => '2025-01-01']]],
            'A non-empty "parent" is required for each installation.',
        ];

        yield 'parent is empty' => [
            ['installations' => [['parent' => '  ']]],
            'A non-empty "parent" is required for each installation.',
        ];

        yield 'parent has an unknown type' => [
            ['installations' => [['parent' => 'bike:b1']]],
            'Invalid installation parent "bike:b1".',
        ];

        yield 'parent has no id' => [
            ['installations' => [['parent' => 'gear:']]],
            'Invalid installation parent "gear:".',
        ];

        yield 'parent component id is invalid' => [
            ['installations' => [['parent' => 'component:wheelset']]],
            'Invalid installation parent "component:wheelset".',
        ];

        yield 'from has the wrong format' => [
            ['installations' => [['parent' => 'gear:b1', 'from' => '01-01-2025']]],
            'Each installation "from" date must be formatted as YYYY-MM-DD.',
        ];

        yield 'from does not exist' => [
            ['installations' => [['parent' => 'gear:b1', 'from' => '2025-02-30']]],
            'Each installation "from" date must be formatted as YYYY-MM-DD.',
        ];

        yield 'until has the wrong format' => [
            ['installations' => [['parent' => 'gear:b1', 'until' => 'tomorrow']]],
            'Each installation "until" date must be formatted as YYYY-MM-DD.',
        ];

        yield 'until is before from' => [
            ['installations' => [['parent' => 'gear:b1', 'from' => '2025-03-15', 'until' => '2025-03-01']]],
            'The "until" date of an installation must be after its "from" date.',
        ];

        yield 'sportTypes is not an array' => [
            ['installations' => [['parent' => 'gear:b1', 'sportTypes' => 'Ride']]],
            'The "sportTypes" of an installation must be an array.',
        ];

        yield 'sportTypes contains an unknown sport type' => [
            ['installations' => [['parent' => 'gear:b1', 'sportTypes' => ['Ride', 'Unicycle']]]],
            'Invalid sport type "Unicycle".',
        ];

        yield 'until equals from' => [
            ['installations' => [['parent' => 'gear:b1', 'from' => '2025-03-15', 'until' => '2025-03-15']]],
            'The "until" date of an installation must be after its "from" date.',
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

            public function doParseNotes(array $payload): ?string
            {
                return self::parseNotes($payload);
            }

            public function doParseInstallations(array $payload): GearComponentInstallations
            {
                return self::parseInstallations($payload);
            }

            public function doParseMaintenanceTasks(array $payload, bool $generateMissingIds): MaintenanceTasks
            {
                return self::parseMaintenanceTasks($payload, $generateMissingIds);
            }
        };
    }
}
