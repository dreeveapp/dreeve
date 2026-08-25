<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Chat;

use App\Application\AppUrl;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\RelativeUrl;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Twig\Environment;

final readonly class ChatFragmentResolver implements FragmentResolver
{
    public const string PATH = 'chat';

    public function __construct(
        private ChatRepository $chatRepository,
        private SettingsRepository $settingsRepository,
        private FormFactoryInterface $formFactory,
        private AppUrl $appUrl,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (self::PATH !== $path) {
            return null;
        }

        if (!$this->settingsRepository->integrations()->isAIIntegrationWithUIEnabled()) {
            return null;
        }

        return new ResolvedFragment(
            path: self::PATH,
            cacheability: Cacheability::for(
                cacheKey: self::PATH,
                cacheTags: CacheTags::of(RootCacheTag::SETTINGS_INTEGRATIONS),
                cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
                ttlInSeconds: 0,
            ),
            render: fn (): string => $this->renderFor(),
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
