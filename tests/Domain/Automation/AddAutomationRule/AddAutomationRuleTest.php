<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\AddAutomationRule;

use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\AddAutomationRule\AddAutomationRule;
use App\Domain\Automation\Condition\ConditionType;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class AddAutomationRuleTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = AddAutomationRule::fromPayload([
            'label' => 'Tag commutes',
            'enabled' => true,
            'stopProcessing' => false,
            'conditions' => [
                ['type' => 'device', 'config' => ['deviceName' => 'Garmin']],
            ],
            'actions' => [
                ['type' => 'setName', 'config' => ['name' => 'Commute']],
            ],
        ]);

        $this->assertSame('Tag commutes', $command->getLabel());
        $this->assertTrue($command->isEnabled());
        $this->assertFalse($command->stopProcessing());
        $this->assertSame(
            [['type' => ConditionType::DEVICE, 'config' => ['deviceName' => 'Garmin']]],
            $command->getConditions()
        );
        $this->assertSame(
            [['type' => ActionType::SET_NAME, 'config' => ['name' => 'Commute']]],
            $command->getActions()
        );
    }

    public function testEnabledDefaultsToTrue(): void
    {
        $command = AddAutomationRule::fromPayload(self::validPayloadWithout('enabled'));

        $this->assertTrue($command->isEnabled());
    }

    public function testEnabledCanBeDisabled(): void
    {
        $command = AddAutomationRule::fromPayload(['enabled' => false] + self::validPayload());

        $this->assertFalse($command->isEnabled());
    }

    public function testStopProcessingDefaultsToTrue(): void
    {
        $command = AddAutomationRule::fromPayload(self::validPayload());

        $this->assertTrue($command->stopProcessing());
    }

    #[TestWith(data: ['false', false])]
    #[TestWith(data: ['true', true])]
    public function testStopProcessingCanBeToggledExplicitly(string $stopProcessing, bool $expectedStopProcessing): void
    {
        $command = AddAutomationRule::fromPayload(['stopProcessing' => $stopProcessing] + self::validPayload());

        $this->assertSame($expectedStopProcessing, $command->stopProcessing());
    }

    public function testLabelIsTrimmed(): void
    {
        $command = AddAutomationRule::fromPayload(['label' => '  Trimmed  '] + self::validPayload());

        $this->assertSame('Trimmed', $command->getLabel());
    }

    public function testConfigDefaultsToEmptyArray(): void
    {
        $command = AddAutomationRule::fromPayload([
            'label' => 'No config',
            'conditions' => [['type' => 'device']],
            'actions' => [['type' => 'setName']],
        ]);

        $this->assertSame([['type' => ConditionType::DEVICE, 'config' => []]], $command->getConditions());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testItThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        AddAutomationRule::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'missing label' => [
            self::validPayloadWithout('label'),
            'A non-empty "label" is required.',
        ];

        yield 'empty conditions' => [
            ['conditions' => []] + self::validPayload(),
            'At least one condition is required.',
        ];

        yield 'empty actions' => [
            ['actions' => []] + self::validPayload(),
            'At least one action is required.',
        ];

        yield 'invalid condition type' => [
            ['conditions' => [['type' => 'nope']]] + self::validPayload(),
            'Invalid condition type "nope".',
        ];

        yield 'invalid action type' => [
            ['actions' => [['type' => 'nope']]] + self::validPayload(),
            'Invalid action type "nope".',
        ];

        yield 'component without a type' => [
            ['conditions' => [['config' => []]]] + self::validPayload(),
            'Each component requires a non-empty "type".',
        ];

        yield 'config that is not an object' => [
            ['conditions' => [['type' => 'device', 'config' => 'nope']]] + self::validPayload(),
            'A component "config" must be an object.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function validPayload(): array
    {
        return [
            'label' => 'Tag commutes',
            'conditions' => [['type' => 'device', 'config' => ['deviceName' => 'Garmin']]],
            'actions' => [['type' => 'setName', 'config' => ['name' => 'Commute']]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function validPayloadWithout(string $key): array
    {
        $payload = self::validPayload();
        unset($payload[$key]);

        return $payload;
    }
}
