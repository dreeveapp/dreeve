<?php

namespace App\Tests\Controller\Page;

use App\Application\AppShell;
use App\Controller\Page\ChatRequestHandler;
use App\Domain\Integration\AI\Chat\AddChatMessage\AddChatMessage;
use App\Domain\Integration\AI\Chat\ChatRepository;
use App\Domain\Integration\AI\Chat\DbalChatRepository;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Infrastructure\CQRS\Command\Bus\SpyCommandBus;
use App\Tests\Infrastructure\Eventing\SpyEventBus;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use Doctrine\DBAL\Connection;
use NeuronAI\Agent\Agent;
use NeuronAI\Agent\AgentInterface;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

#[AllowMockObjectsWithoutExpectations]
class ChatRequestHandlerTest extends AdminWebTestCase
{
    use MatchesSnapshots;

    /**
     * @var Stub&AgentInterface
     */
    private Stub $neuronAIAgent;
    /**
     * @var MockObject&ChatRepository
     */
    private MockObject $chatRepository;

    public function testRender(): void
    {
        $this->enableAssistant(true);

        $this->client->request('GET', '/chat');

        $this->assertResponseIsSuccessful();
        $this->assertResponseNotHasHeader('X-Dreeve-Cache');
        $this->assertResponseHeaderSame('Cache-Control', 'max-age=0, must-revalidate, no-store, private');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItIsNotFoundWhenTheAssistantIsDisabled(): void
    {
        $this->enableAssistant(false);

        $this->client->request('GET', '/chat');

        $this->assertResponseStatusCodeSame(404);
    }

    private function enableAssistant(bool $enabled): void
    {
        $this->getContainer()->get(DbalSettingsRepository::class)->saveGroup(SettingsGroup::INTEGRATIONS, [
            'ai' => [
                'enabled' => true,
                'enableUI' => $enabled,
                'provider' => 'openAI',
                'configuration' => [
                    'key' => 'my-key',
                    'model' => 'cool-model',
                ],
            ],
        ]);
    }

    public function testItOnlyRendersTheAdminLinkForAuthenticatedVisitors(): void
    {
        $this->enableAssistant(true);

        $this->client->request('GET', '/chat');
        $this->assertStringNotContainsString(
            'admin/settings/integrations',
            (string) $this->client->getResponse()->getContent(),
        );

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/chat');
        $this->assertStringContainsString(
            'admin/settings/integrations?redirectTo=%2Fchat',
            (string) $this->client->getResponse()->getContent(),
        );
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

        $this->expectException(NotFoundHttpException::class);
        $requestHandler->clearChat();
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testChatSse(): void
    {
        $chatRepository = new DbalChatRepository(
            connection: $this->getContainer()->get(Connection::class),
            clock: PausedClock::on(SerializableDateTime::fromString('2025-05-05')),
            settingsRepository: $this->getContainer()->get(SettingsRepository::class),
        );

        $agent = Agent::make(workflowId: 'chat')->setAiProvider(
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
            connection: $this->getContainer()->get(Connection::class),
            clock: PausedClock::on(SerializableDateTime::fromString('2025-05-05')),
            settingsRepository: $this->getContainer()->get(SettingsRepository::class),
        );

        $agent = Agent::make(workflowId: 'chat')->setAiProvider(
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
        $this->assertStringNotContainsString('#0 ', $content);
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

        $this->expectException(NotFoundHttpException::class);
        $requestHandler->chatSse(new Request(query: ['message' => 'What is my FTP?']));
    }

    private function buildRequestHandler(bool $aiUIEnabled): ChatRequestHandler
    {
        return new ChatRequestHandler(
            neuronAIAgent: $this->neuronAIAgent,
            chatRepository: $this->chatRepository,
            commandBus: $this->getContainer()->get(CommandBus::class),
            twig: $this->getContainer()->get(Environment::class),
            settingsRepository: $this->buildSettingsRepository($aiUIEnabled),
            formFactory: $this->getContainer()->get(FormFactoryInterface::class),
            appShell: $this->getContainer()->get(AppShell::class),
        );
    }

    private function buildRequestHandlerForSse(
        DbalChatRepository $chatRepository,
        AgentInterface $agent,
        CommandBus $commandBus,
    ): ChatRequestHandler {
        return new ChatRequestHandler(
            neuronAIAgent: $agent,
            chatRepository: $chatRepository,
            commandBus: $commandBus,
            twig: $this->getContainer()->get(Environment::class),
            settingsRepository: $this->buildSettingsRepository(true),
            formFactory: $this->getContainer()->get(FormFactoryInterface::class),
            appShell: $this->getContainer()->get(AppShell::class),
        );
    }

    private function buildSettingsRepository(bool $aiUIEnabled): SettingsRepository
    {
        $settingsRepository = new DbalSettingsRepository($this->getContainer()->get(Connection::class), new SpyEventBus());
        $settingsRepository->saveGroup(SettingsGroup::INTEGRATIONS, [
            'ai' => [
                'enabled' => true,
                'enableUI' => $aiUIEnabled,
                'provider' => 'openAI',
                'configuration' => [
                    'key' => 'my-key',
                    'model' => 'cool-model',
                ],
            ],
        ]);

        return $settingsRepository;
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->neuronAIAgent = $this->createStub(AgentInterface::class);
        $this->chatRepository = $this->createMock(ChatRepository::class);
    }
}
