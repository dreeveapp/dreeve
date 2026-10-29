<?php

declare(strict_types=1);

namespace App\Controller\Page\Rewind;

use App\Application\Navigation\NavigationSection;
use App\Application\PageMetadata\PageMetadata;
use App\Application\PageRenderer;
use App\Domain\Rewind\FindAvailableRewindOptions\FindAvailableRewindOptions;
use App\Domain\Rewind\RewindCacheTags;
use App\Domain\Rewind\RewindItemsBuilder;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
final readonly class RewindRequestHandler
{
    private const string BASE_PATH = 'rewind';

    public function __construct(
        private QueryBus $queryBus,
        private RewindItemsBuilder $rewindItemsBuilder,
        private PageRenderer $pageRenderer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/rewind/{rewindOption}', name: 'rewind', requirements: ['rewindOption' => '[^/]+'], defaults: ['rewindOption' => null], methods: ['GET'])]
    public function handle(?string $rewindOption): HtmlResponse
    {
        $availableRewindOptions = $this->queryBus->ask(new FindAvailableRewindOptions())->getAvailableOptions();
        if ([] === $availableRewindOptions) {
            throw new NotFoundHttpException('Not found');
        }

        $rewindOption ??= $availableRewindOptions[0];
        if (!in_array($rewindOption, $availableRewindOptions, true)) {
            throw new NotFoundHttpException('Not found');
        }

        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: sprintf('%s.%s', self::BASE_PATH, $rewindOption),
                cacheTags: RewindCacheTags::forOption($rewindOption),
            ),
            render: fn (): string => $this->renderFor($rewindOption),
            navigationSection: NavigationSection::REWIND,
            metadata: new PageMetadata(
                path: $this->urlGenerator->generate('rewind', ['rewindOption' => $rewindOption]),
                title: $this->labelFor($rewindOption),
                description: FindAvailableRewindOptions::ALL_TIME === $rewindOption
                    ? $this->translator->trans('A look back at all activities')
                    : $this->translator->trans('A look back at {year}', ['{year}' => $rewindOption]),
            ),
        );
    }

    private function renderFor(string $rewindOption): string
    {
        $availableRewindOptionsResponse = $this->queryBus->ask(new FindAvailableRewindOptions());

        return $this->twig->load('html/rewind/rewind.html.twig')->render([
            'availableRewindOptions' => $availableRewindOptionsResponse->getAvailableOptions(),
            'activeRewindOption' => $rewindOption,
            'rewindItems' => $this->rewindItemsBuilder->build(
                rewindOption: $rewindOption,
                yearsToQuery: $availableRewindOptionsResponse->getYearsToQuery($rewindOption),
            ),
            'isAllTimeRewind' => FindAvailableRewindOptions::ALL_TIME === $rewindOption,
        ]);
    }

    private function labelFor(string $rewindOption): string
    {
        if (FindAvailableRewindOptions::ALL_TIME === $rewindOption) {
            return $this->translator->trans('All time');
        }

        return sprintf('%s %s', $this->translator->trans('Rewind'), $rewindOption);
    }
}
