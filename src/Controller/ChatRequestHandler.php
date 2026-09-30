<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\AppShell;
use App\Application\AppUrl;
use App\Domain\Integration\AI\Chat\ChatRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Http\HtmlResponse;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\RelativeUrl;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ChatRequestHandler
{
    public function __construct(
        private ChatRepository $chatRepository,
        private SettingsRepository $settingsRepository,
        private FormFactoryInterface $formFactory,
        private AppUrl $appUrl,
        private CacheableRenderer $cacheableRenderer,
        private AppShell $appShell,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/chat', name: 'chat', methods: ['GET'], priority: 3)]
    public function handle(): Response
    {
        if (!$this->settingsRepository->integrations()->isAIIntegrationWithUIEnabled()) {
            throw new NotFoundHttpException('Not found');
        }

        $render = $this->cacheableRenderer->render(new ResolvedFragment(
            path: 'chat',
            cacheability: Cacheability::for(
                cacheKey: 'chat',
                cacheTags: CacheTags::of(RootCacheTag::SETTINGS_INTEGRATIONS),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
                ttlInSeconds: 0,
            ),
            render: fn (): string => $this->renderFor(),
        ));

        return new HtmlResponse(
            $this->appShell->render(
                content: $render->getContent() ?? '',
                navigationSection: null,
                openGraph: null,
            ),
            headers: [...$render->getCacheHeaders(), 'Cache-Control' => 'private, no-store'],
        );
    }

    private function renderFor(): string
    {
        $form = $this->formFactory->createBuilder()
            ->setAction(RelativeUrl::from('/ai/chat/user-message', $this->appUrl)->toRelativeUrl())
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
