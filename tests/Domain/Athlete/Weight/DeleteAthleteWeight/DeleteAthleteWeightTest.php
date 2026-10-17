<?php

declare(strict_types=1);

namespace App\Tests\Domain\Athlete\Weight\DeleteAthleteWeight;

use App\Domain\Athlete\Weight\DeleteAthleteWeight\DeleteAthleteWeight;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeleteAthleteWeightTest extends TestCase
{
    public function testFromPayload(): void
    {
        $command = DeleteAthleteWeight::fromPayload(['on' => '  2025-01-01  ']);

        $this->assertSame('delete-athlete-weight', DeleteAthleteWeight::getCommandName());
        $this->assertEquals(SerializableDateTime::fromString('2025-01-01 00:00:00'), $command->getOn());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        DeleteAthleteWeight::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'on is missing' => [[], 'An "on" date is required.'];
        yield 'on is not a string' => [['on' => 20250101], 'An "on" date is required.'];
        yield 'on is not a valid date' => [['on' => 'not-a-date'], 'The "on" date must be in YYYY-MM-DD format.'];
    }
}
