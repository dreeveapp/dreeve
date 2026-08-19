<?php

namespace App\Tests\Controller;

use App\Controller\AIChatRequestHandler;
use App\Domain\Integration\AI\Chat\AddChatMessage\AddChatMessage;
use App\Domain\Integration\AI\Chat\ChatRepository;
use App\Domain\Integration\AI\Chat\DbalChatRepository;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Infrastructure\CQRS\Command\Bus\SpyCommandBus;
use App\Tests\Infrastructure\Eventing\SpyEventBus;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\AgentInterface;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

#[AllowMockObjectsWithoutExpectations]
class AIChatRequestHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private KeyValueStore $keyValueStore;
    /**
     * @var Stub&AgentInterface
     */
    private Stub $neuronAIAgent;
    /**
     * @var MockObject&ChatRepository
     */
    private MockObject $chatRepository;

    public function testClearChat(): void
    {
        $requestHandler = $this->buildRequestHandler(
            true
        );

        $this->chatRepository
            ->expects($this->once())
            ->method('clear');

        $this->assertEquals(
            204,
            $requestHandler->clearChat()->getStatusCode()
        );
    }

    public function testClearChatAINotEnabled(): void
    {
        $this->chatRepository
            ->expects($this->never())
            ->method('clear');

        $requestHandler = $this->buildRequestHandler(
            false
        );

        $this->assertMatchesHtmlSnapshot($requestHandler->clearChat()->getContent());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testChatSse(): void
    {
        $chatRepository = new DbalChatRepository(
            connection: $this->getConnection(),
            clock: PausedClock::on(SerializableDateTime::fromString('2025-05-05')),
            settingsRepository: $this->getContainer()->get(SettingsRepository::class),
        );

        $agent = Agent::make()->setAiProvider(
            new FakeAIProvider(new AssistantMessage('Hello World'))
        );

        $requestHandler = $this->buildRequestHandlerForSse(
            chatRepository: $chatRepository,
            agent: $agent,
            commandBus: new SpyCommandBus(),
        );

        $request = new Request(query: ['message' => 'What is my FTP?']);
        $response = $requestHandler->chatSse($request);

        $this->assertInstanceOf(EventStreamResponse::class, $response);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('event: fullMessage', $content);
        $this->assertStringContainsString('event: removeThinking', $content);
        $this->assertStringContainsString('event: agentResponse', $content);
        $this->assertStringContainsString('Hello', $content);
        $this->assertStringContainsString('event: done', $content);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testChatSseOnError(): void
    {
        $chatRepository = new DbalChatRepository(
            connection: $this->getConnection(),
            clock: PausedClock::on(SerializableDateTime::fromString('2025-05-05')),
            settingsRepository: $this->getContainer()->get(SettingsRepository::class),
        );

        $agent = Agent::make()->setAiProvider(
            new FakeAIProvider()
        );

        $spyCommandBus = new SpyCommandBus();

        $requestHandler = $this->buildRequestHandlerForSse(
            chatRepository: $chatRepository,
            agent: $agent,
            commandBus: $spyCommandBus,
        );

        $request = new Request(query: ['message' => 'What is my FTP?']);
        $response = $requestHandler->chatSse($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('event: fullMessage', $content);
        $this->assertStringContainsString('event: removeThinking', $content);
        $this->assertStringContainsString('Oh no, I made a booboo', $content);
        $this->assertStringContainsString('event: done', $content);

        $dispatchedCommands = $spyCommandBus->getDispatchedCommands();
        $this->assertCount(1, $dispatchedCommands);
        $this->assertInstanceOf(AddChatMessage::class, $dispatchedCommands[0]);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testChatSseAINotEnabled(): void
    {
        $requestHandler = $this->buildRequestHandler(
            false
        );

        $request = new Request(query: ['message' => 'What is my FTP?']);
        $this->assertMatchesHtmlSnapshot($requestHandler->chatSse($request)->getContent());
    }

    private function buildRequestHandler(bool $aiUIEnabled): AIChatRequestHandler
    {
        return new AIChatRequestHandler(
            neuronAIAgent: $this->neuronAIAgent,
            chatRepository: $this->chatRepository,
            commandBus: $this->getContainer()->get(CommandBus::class),
            twig: $this->getContainer()->get(Environment::class),
            settingsRepository: $this->buildSettingsRepository($aiUIEnabled),
        );
    }

    private function buildRequestHandlerForSse(
        DbalChatRepository $chatRepository,
        AgentInterface $agent,
        CommandBus $commandBus,
    ): AIChatRequestHandler {
        return new AIChatRequestHandler(
            neuronAIAgent: $agent,
            chatRepository: $chatRepository,
            commandBus: $commandBus,
            twig: $this->getContainer()->get(Environment::class),
            settingsRepository: $this->buildSettingsRepository(true),
        );
    }

    private function buildSettingsRepository(bool $aiUIEnabled): SettingsRepository
    {
        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $keyValueStore->save(KeyValue::fromState(
            SettingsGroup::INTEGRATIONS->keyValueKey(),
            Value::fromString(Json::encode([
                'ai' => [
                    'enabled' => true,
                    'enableUI' => $aiUIEnabled,
                    'provider' => 'openAI',
                    'configuration' => [
                        'key' => 'my-key',
                        'model' => 'cool-model',
                    ],
                ],
            ])),
        ));

        return new KeyValueBasedSettingsRepository($keyValueStore, new SpyEventBus());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $this->neuronAIAgent = $this->createStub(AgentInterface::class);
        $this->chatRepository = $this->createMock(ChatRepository::class);
    }
}
