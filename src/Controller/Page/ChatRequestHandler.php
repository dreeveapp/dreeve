<?php

declare(strict_types=1);

namespace App\Controller\Page;

use App\Application\AppShell;
use App\Application\PageMetadata\PageMetadata;
use App\Domain\Integration\AI\Chat\AddChatMessage\AddChatMessage;
use App\Domain\Integration\AI\Chat\ChatRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\Http\PrivateNoStoreHtmlResponse;
use App\Infrastructure\Http\ServerSentEvent;
use App\Infrastructure\Renderer\MarkdownRenderer;
use App\Infrastructure\Serialization\Json;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\Role;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
final readonly class ChatRequestHandler
{
    public function __construct(
        private AgentInterface $chatAgent,
        private ChatRepository $chatRepository,
        private CommandBus $commandBus,
        private SettingsRepository $settingsRepository,
        private FormFactoryInterface $formFactory,
        private AppShell $appShell,
        private Environment $twig,
        private MarkdownRenderer $markdownRenderer,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/chat', name: 'chat', methods: ['GET'])]
    public function handle(): PrivateNoStoreHtmlResponse
    {
        if (!$this->settingsRepository->integrations()->isAIIntegrationEnabled()) {
            throw new NotFoundHttpException('Not found');
        }

        return new PrivateNoStoreHtmlResponse($this->appShell->render(
            content: $this->renderFor(),
            navigationSection: null,
            metadata: new PageMetadata(
                path: $this->urlGenerator->generate('chat'),
                title: $this->translator->trans('Virtual workout assistant'),
                description: $this->translator->trans('Chat with an AI assistant about your training'),
            ),
        ));
    }

    #[Route(path: '/chat/clear', name: 'ai_chat_clear', methods: ['POST'], priority: 2)]
    public function clearChat(): Response
    {
        if (!$this->settingsRepository->integrations()->isAIIntegrationEnabled()) {
            throw new NotFoundHttpException('Not found');
        }

        $this->chatRepository->clear();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/chat/sse', name: 'ai_chat_sse', methods: ['GET'], priority: 2)]
    public function chatSse(Request $request): EventStreamResponse
    {
        if (!$this->settingsRepository->integrations()->isAIIntegrationEnabled()) {
            throw new NotFoundHttpException('Not found');
        }

        return new EventStreamResponse(function (EventStreamResponse $response) use ($request): void {
            $message = $request->query->get('message');
            assert(is_string($message));

            $response->sendEvent(new ServerSentEvent(
                data: $this->twig->render('html/chat/message.html.twig', [
                    'chatMessage' => $this->chatRepository->buildMessage(
                        message: $message,
                        messageRole: Role::User,
                    ),
                    'isThinking' => false,
                ]),
                type: 'fullMessage'
            ));

            $response->sendEvent(new ServerSentEvent(
                data: $this->twig->render('html/chat/message.html.twig', [
                    'chatMessage' => $this->chatRepository->buildMessage(
                        message: '__PLACEHOLDER__',
                        messageRole: Role::Assistant,
                    ),
                    'isThinking' => true,
                ]),
                type: 'fullMessage'
            ));

            $this->commandBus->dispatch(new AddChatMessage(
                message: $message,
                messageRole: Role::User,
            ));

            $fullResponse = '';
            try {
                $execution = $this->chatAgent->call(Message::ofUser($message), ['stream' => true]);
                foreach ($execution->asStream() as $delta) {
                    if (!$delta instanceof TextDelta) {
                        continue;  // @codeCoverageIgnore
                    }
                    $response->sendEvent(new ServerSentEvent(
                        data: '',
                        type: 'removeThinking'
                    ));

                    $fullResponse .= $delta->getText();

                    $response->sendEvent(new ServerSentEvent(
                        data: $delta->getText(),
                        type: 'agentResponse'
                    ));
                }
            } catch (\Throwable $e) {
                $response->sendEvent(new ServerSentEvent(
                    data: '',
                    type: 'removeThinking'
                ));

                $fullResponse = 'Oh no, I made a booboo...'."\n\n".trim((string) preg_replace('/\s+/', ' ', $e->getMessage()));

                $response->sendEvent(new ServerSentEvent(
                    data: $fullResponse,
                    type: 'agentResponse'
                ));
            }

            $this->commandBus->dispatch(new AddChatMessage(
                message: $fullResponse,
                messageRole: Role::Assistant,
            ));

            $response->sendEvent(new ServerSentEvent(
                data: $this->markdownRenderer->render($fullResponse),
                type: 'done'
            ));
        });
    }

    private function renderFor(): string
    {
        $form = $this->formFactory->createBuilder()
            ->add('message', TextType::class, [
                'label' => 'Message',
                'required' => true,
            ])
            ->add('submit', SubmitType::class)
            ->getForm();

        return $this->twig->load('html/chat/chat.html.twig')->render([
            'chatHistory' => $this->chatRepository->findAll(),
            'form' => $form->createView(),
            'chatCommands' => Json::encode($this->settingsRepository->integrations()->getChatCommands()),
        ]);
    }
}
