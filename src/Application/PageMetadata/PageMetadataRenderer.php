<?php

declare(strict_types=1);

namespace App\Application\PageMetadata;

use App\Domain\Activity\FindActivityTotals\FindActivityTotals;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Measurement\ProvideMeasurementFormats;
use App\Infrastructure\Measurement\Time\Hour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class PageMetadataRenderer
{
    use ProvideMeasurementFormats;

    public function __construct(
        private SettingsRepository $settingsRepository,
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
        private CacheableRenderer $cacheableRenderer,
        private QueryBus $queryBus,
        private Environment $twig,
    ) {
    }

    public function render(PageMetadata $metadata): string
    {
        if (!($request = $this->requestStack->getCurrentRequest()) instanceof Request) {
            throw new \LogicException('Page metadata can only be rendered for a request');
        }

        $description = $metadata->getDescription();
        if (null === $description) {
            $description = $this->cacheableRenderer->render(
                cacheability: Cacheability::for(
                    cacheKey: 'app-shell.metatag.description',
                    cacheTags: CacheTags::of(
                        RootCacheTag::ACTIVITIES,
                        RootCacheTag::SETTINGS_GENERAL,
                        RootCacheTag::SETTINGS_APPEARANCE,
                    ),
                ),
                render: function (): string {
                    $activityTotals = $this->queryBus->ask(new FindActivityTotals());
                    $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();

                    return implode(' · ', [
                        (string) $this->settingsRepository->general()->getAthlete()->getName(),
                        $this->translator->trans('{numberOfActivities} activities', ['{numberOfActivities}' => $this->formatNumber($activityTotals->getTotalActivities(), 0)]),
                        $this->formatUnitWithSymbol($activityTotals->getTotalDistance()->toUnitSystem($unitSystem), 0),
                        $this->formatUnitWithSymbol(Hour::from($activityTotals->getMovingTimeInHours()), 0),
                    ]);
                },
            )->getContent();
        }

        return $this->twig->load('html/page-metadata.html.twig')->render([
            'title' => $this->translate($metadata->getTitle()),
            'description' => $this->translate($description ?? ''),
            'path' => $this->urlGenerator->generate(
                name: $request->attributes->getString('_route'),
                parameters: $request->attributes->all('_route_params')
            ),
            'imagePath' => $metadata->getImagePath(),
        ]);
    }

    private function translate(string|TranslatableInterface $message): string
    {
        return $message instanceof TranslatableInterface ? $message->trans($this->translator) : $message;
    }
}
