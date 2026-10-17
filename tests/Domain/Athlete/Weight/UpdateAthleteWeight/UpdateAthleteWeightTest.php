<?php

declare(strict_types=1);

namespace App\Tests\Domain\Athlete\Weight\UpdateAthleteWeight;

use App\Domain\Athlete\Weight\UpdateAthleteWeight\UpdateAthleteWeight;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpdateAthleteWeightTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = UpdateAthleteWeight::fromPayload([
            'on' => '  2025-01-01  ',
            'weight' => '70.5',
        ]);

        $this->assertSame('update-athlete-weight', UpdateAthleteWeight::getCommandName());
        $this->assertEquals(SerializableDateTime::fromString('2025-01-01 00:00:00'), $command->getOn());
        $this->assertSame(70.5, $command->getWeight());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        UpdateAthleteWeight::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'on is missing' => [['weight' => 70], 'An "on" date is required.'];
        yield 'on is not a string' => [['on' => ['2025-01-01'], 'weight' => 70], 'An "on" date is required.'];
        yield 'on is not a valid date' => [['on' => '01/01/2025', 'weight' => 70], 'The "on" date must be in YYYY-MM-DD format.'];
        yield 'weight is missing' => [['on' => '2025-01-01'], 'The "weight" must be a positive number.'];
        yield 'weight is not numeric' => [['on' => '2025-01-01', 'weight' => 'heavy'], 'The "weight" must be a positive number.'];
        yield 'weight is zero' => [['on' => '2025-01-01', 'weight' => '0'], 'The "weight" must be a positive number.'];
    }
}
