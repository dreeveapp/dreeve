<?php

namespace App\Tests\Domain\Integration\AI\Chat;

use App\Domain\Integration\AI\Chat\ChatCommands;
use App\Domain\Integration\AI\Chat\InvalidChatCommandsConfig;
use App\Infrastructure\Serialization\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class ChatCommandsTest extends TestCase
{
    public function testItShouldSerialize(): void
    {
        $yml = self::getValidYml();

        $this->assertEquals(
            [
                '/analyse-last-workout' => 'You are my bike trainer. Please analyze my most recent ride with regard to aspects such as heart rate, power (if available). Please give me an assessment of my performance level and possible improvements for future training sessions.',
                '/compare-last-two-weeks' => 'You are my bike trainer. Please compare my workouts and performance of the last 7 days with the 7 days before and give a short assessment.',
            ],
            Json::encodeAndDecode(ChatCommands::fromArray($yml))
        );
    }

    #[DataProvider('provideInvalidConfig')]
    public function testFromYmlStringItShouldThrow(array $yml, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidChatCommandsConfig($expectedException));
        ChatCommands::fromArray($yml);
    }

    public static function provideInvalidConfig(): iterable
    {
        $yml = self::getValidYml();
        unset($yml[0]['command']);
        yield 'missing "command" key' => [$yml, '"command" property is required'];

        $yml = self::getValidYml();
        unset($yml[0]['message']);
        yield 'missing "message" key' => [$yml, '"message" property is required'];

        $yml = self::getValidYml();
        $yml[0]['command'] = [];
        yield 'invalid "command" key' => [$yml, 'command must be a string'];

        $yml = self::getValidYml();
        $yml[0]['command'] = '/my-command';
        yield 'invalid "command" key with slash' => [$yml, 'commands must not start with a slash. (/my-command)'];

        $yml = self::getValidYml();
        $yml[0]['message'] = [];
        yield 'invalid "message" key' => [$yml, 'message must be a string'];

        $yml = self::getValidYml();
        $yml[0]['command'] = '';
        yield 'empty "command" key' => [$yml, 'command and message cannot be empty'];

        $yml = self::getValidYml();
        $yml[0]['message'] = '';
        yield 'empty "message" key' => [$yml, 'command and message cannot be empty'];
    }

    private static function getValidYml(): array
    {
        return Yaml::parse(<<<YML
- command: 'analyse-last-workout'
  message: 'You are my bike trainer. Please analyze my most recent ride with regard to aspects such as heart rate, power (if available). Please give me an assessment of my performance level and possible improvements for future training sessions.'
- command: 'compare-last-two-weeks'
  message:  'You are my bike trainer. Please compare my workouts and performance of the last 7 days with the 7 days before and give a short assessment.'
YML
        );
    }
}
