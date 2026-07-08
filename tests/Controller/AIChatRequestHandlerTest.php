<?php

namespace App\Tests\Controller;

use App\Application\AppUrl;
use App\Controller\AIChatRequestHandler;
use App\Domain\Integration\AI\Chat\AddChatMessage\AddChatMessage;
use App\Domain\Integration\AI\Chat\ChatMessage;
use App\Domain\Integration\AI\Chat\ChatMessageId;
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
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use League\Flysystem\FilesystemOperator;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\AgentInterface;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

class AIChatRequestHandlerTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private FilesystemOperator $buildStorage;
    private Stub $neuronAIAgent;
    private MockObject $chatRepository;

    public function testHandle(): void
    {
        $this->buildStorage->write('index.html', 'I am the index', []);

        $this->chatRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([new ChatMessage(
                messageId: ChatMessageId::random(),
                message: 'message',
                messageRole: MessageRole::USER,
                on: SerializableDateTime::fromString('2025-05-05')
            )->withFirstLetterOfFirstName('R')]);

        $requestHandler = $this->buildRequestHandler(
            true
        );

        $this->assertMatchesHtmlSnapshot($requestHandler->handle()->getContent());
    }

    public function testHandleNoIndexFound(): void
    {
        $this->chatRepository
            ->expects($this->never())
            ->method('findAll');

        $requestHandler = $this->buildRequestHandler(
            true
        );

        $this->assertMatchesHtmlSnapshot($requestHandler->handle()->getContent());
    }

    public function testHandleAINotEnabled(): void
    {
        $this->buildStorage->write('index.html', 'I am the index', []);

        $this->chatRepository
            ->expects($this->never())
            ->method('findAll');

        $requestHandler = $this->buildRequestHandler(
            false
        );

        $this->assertMatchesHtmlSnapshot($requestHandler->handle()->getContent());
    }

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
            buildHtmlStorage: $this->buildStorage,
            neuronAIAgent: $this->neuronAIAgent,
            chatRepository: $this->chatRepository,
            commandBus: $this->getContainer()->get(CommandBus::class),
            appUrl: AppUrl::fromString('http://localhost'),
            formFactory: $this->getContainer()->get(FormFactoryInterface::class),
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
            buildHtmlStorage: $this->buildStorage,
            neuronAIAgent: $agent,
            chatRepository: $chatRepository,
            commandBus: $commandBus,
            appUrl: AppUrl::fromString('http://localhost'),
            formFactory: $this->getContainer()->get(FormFactoryInterface::class),
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

        return new KeyValueBasedSettingsRepository($keyValueStore);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildStorage = $this->getContainer()->get('build_html.storage');
        $this->neuronAIAgent = $this->createStub(AgentInterface::class);
        $this->chatRepository = $this->createMock(ChatRepository::class);
    }
}
