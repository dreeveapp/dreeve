<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\CQRS\Command\Deserialize;

use App\Infrastructure\CQRS\Command\Deserialize\CommandDeserializer;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommandRegistry;
use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\TestCase;

class CommandDeserializerTest extends TestCase
{
    private CommandDeserializer $commandDeserializer;

    public function testDeserialize(): void
    {
        $command = $this->commandDeserializer->deserialize(Json::encode([
            'commandName' => 'test-deserializable-command',
            'payload' => [
                'message' => 'Hello',
                'url' => 'https://example.com',
            ],
        ]));

        $this->assertInstanceOf(TestDeserializableCommand::class, $command);
        $this->assertEquals('Hello', $command->getMessage());
        $this->assertEquals('https://example.com', (string) $command->getUrl());
    }

    public function testDeserializeWithInvalidPayload(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload('Expected a JSON object with a "commandName" and "payload".'));

        $this->commandDeserializer->deserialize(Json::encode(['no' => 'command']));
    }

    public function testDeserializeWithUnknownCommand(): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::unknownCommand('not-a-known-command'));

        $this->commandDeserializer->deserialize(Json::encode([
            'commandName' => 'not-a-known-command',
            'payload' => [],
        ]));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandDeserializer = new CommandDeserializer(new DeserializableCommandRegistry([
            'test-deserializable-command' => TestDeserializableCommand::class,
        ]));
    }
}
