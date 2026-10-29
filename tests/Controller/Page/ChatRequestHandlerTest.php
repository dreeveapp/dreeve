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
use App\Infrastructure\Renderer\MarkdownRenderer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Infrastructure\CQRS\Command\Bus\SpyCommandBus;
use App\Tests\Infrastructure\Eventing\SpyEventBus;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\Stub;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class ChatRequestHandlerTest extends AdminWebTestCase
{
    use MatchesSnapshots;

    /**
     * @var Stub&AgentInterface
     */
    private Stub $chatAgent;

    public function testRender(): void
    {
        $this->enableAssistant(true, commands: [
            ['command' => 'review-cardio', 'message' => "Don't forget that commute activities are part of my training"],
        ]);

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

    /**
     * @param array<int, array{command: string, message: string}> $commands
     */
    private function enableAssistant(bool $enabled, array $commands = []): void
    {
        $this->getContainer()->get(DbalSettingsRepository::class)->saveGroup(SettingsGroup::INTEGRATIONS, [
            'ai' => [
                'enabled' => $enabled,
                'provider' => 'openAI',
                'configuration' => [
                    'key' => 'my-key',
                    'model' => 'cool-model',
                ],
                'agent' => [
                    'commands' => $commands,
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
        $chatRepository = $this->createMock(ChatRepository::class);
        $chatRepository
            ->expects($this->once())
            ->method('clear');

        $requestHandler = $this->buildRequestHandler(
            aiEnabled: true,
            chatRepository: $chatRepository,
        );

        $this->assertEquals(
            204,
            $requestHandler->clearChat()->getStatusCode()
        );
    }

    public function testClearChatAINotEnabled(): void
    {
        $chatRepository = $this->createMock(ChatRepository::class);
        $chatRepository
            ->expects($this->never())
            ->method('clear');

        $requestHandler = $this->buildRequestHandler(
            aiEnabled: false,
            chatRepository: $chatRepository,
        );

        $this->expectException(NotFoundHttpException::class);
        $requestHandler->clearChat();
    }

    public function testChatSse(): void
    {
        $chatRepository = new DbalChatRepository(
            connection: $this->getContainer()->get(Connection::class),
            clock: PausedClock::on(SerializableDateTime::fromString('2025-05-05')),
            settingsRepository: $this->getContainer()->get(SettingsRepository::class),
        );

        $agent = new Agent(
            new InMemoryPlatform(static fn (): StreamResult => new StreamResult((static function (): \Generator {
                yield new TextDelta('<img src=x onerror=alert(1)> **Hello**');
                yield new TextDelta(' World');
            })())),
            'model',
        );

        $spyCommandBus = new SpyCommandBus();

        $requestHandler = $this->buildRequestHandlerForSse(
            chatRepository: $chatRepository,
            agent: $agent,
            commandBus: $spyCommandBus,
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

        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $content);
        $this->assertStringContainsString('<strong>Hello</strong>', $content);

        $this->assertEquals(
            [
                new AddChatMessage('What is my FTP?', Role::User),
                new AddChatMessage('<img src=x onerror=alert(1)> **Hello** World', Role::Assistant),
            ],
            $spyCommandBus->getDispatchedCommands(),
        );
    }

    public function testChatSseOnError(): void
    {
        $chatRepository = new DbalChatRepository(
            connection: $this->getContainer()->get(Connection::class),
            clock: PausedClock::on(SerializableDateTime::fromString('2025-05-05')),
            settingsRepository: $this->getContainer()->get(SettingsRepository::class),
        );

        $agent = new Agent(
            new InMemoryPlatform(static fn (): never => throw new \RuntimeException('Invalid API key')),
            'model',
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

        $this->assertEquals(
            [
                new AddChatMessage('What is my FTP?', Role::User),
                new AddChatMessage("Oh no, I made a booboo...\n\nInvalid API key", Role::Assistant),
            ],
            $spyCommandBus->getDispatchedCommands(),
        );
    }

    public function testChatSseAINotEnabled(): void
    {
        $requestHandler = $this->buildRequestHandler(
            aiEnabled: false,
            chatRepository: $this->createStub(ChatRepository::class),
        );

        $this->expectException(NotFoundHttpException::class);
        $requestHandler->chatSse(new Request(query: ['message' => 'What is my FTP?']));
    }

    private function buildRequestHandler(bool $aiEnabled, ChatRepository $chatRepository): ChatRequestHandler
    {
        return new ChatRequestHandler(
            chatAgent: $this->chatAgent,
            chatRepository: $chatRepository,
            commandBus: $this->getContainer()->get(CommandBus::class),
            twig: $this->getContainer()->get(Environment::class),
            settingsRepository: $this->buildSettingsRepository($aiEnabled),
            formFactory: $this->getContainer()->get(FormFactoryInterface::class),
            appShell: $this->getContainer()->get(AppShell::class),
            markdownRenderer: new MarkdownRenderer(),
            urlGenerator: $this->getContainer()->get(UrlGeneratorInterface::class),
            translator: $this->getContainer()->get(TranslatorInterface::class),
        );
    }

    private function buildRequestHandlerForSse(
        DbalChatRepository $chatRepository,
        AgentInterface $agent,
        CommandBus $commandBus,
    ): ChatRequestHandler {
        return new ChatRequestHandler(
            chatAgent: $agent,
            chatRepository: $chatRepository,
            commandBus: $commandBus,
            twig: $this->getContainer()->get(Environment::class),
            settingsRepository: $this->buildSettingsRepository(true),
            formFactory: $this->getContainer()->get(FormFactoryInterface::class),
            appShell: $this->getContainer()->get(AppShell::class),
            markdownRenderer: new MarkdownRenderer(),
            urlGenerator: $this->getContainer()->get(UrlGeneratorInterface::class),
            translator: $this->getContainer()->get(TranslatorInterface::class),
        );
    }

    private function buildSettingsRepository(bool $aiEnabled): SettingsRepository
    {
        $settingsRepository = new DbalSettingsRepository($this->getContainer()->get(Connection::class), new SpyEventBus());
        $settingsRepository->saveGroup(SettingsGroup::INTEGRATIONS, [
            'ai' => [
                'enabled' => $aiEnabled,
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

        $this->chatAgent = $this->createStub(AgentInterface::class);
    }
}
