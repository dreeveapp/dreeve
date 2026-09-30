<?php

declare(strict_types=1);

namespace App\Controller\Rewind;

use App\Application\AppShell;
use App\Application\Navigation\NavigationSection;
use App\Domain\Rewind\FindAvailableRewindOptions\FindAvailableRewindOptions;
use App\Domain\Rewind\RewindCacheTags;
use App\Domain\Rewind\RewindItemsBuilder;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableContent;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class RewindRequestHandler
{
    private const string BASE_PATH = 'rewind';

    public function __construct(
        private QueryBus $queryBus,
        private RewindItemsBuilder $rewindItemsBuilder,
        private CacheableRenderer $cacheableRenderer,
        private AppShell $appShell,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/rewind/{rewindOption}', name: 'rewind', requirements: ['rewindOption' => '[^/]+'], defaults: ['rewindOption' => null], methods: ['GET'], priority: 3)]
    public function handle(?string $rewindOption): Response
    {
        $availableRewindOptions = $this->queryBus->ask(new FindAvailableRewindOptions())->getAvailableOptions();
        if ([] === $availableRewindOptions) {
            throw new NotFoundHttpException('Not found');
        }

        $rewindOption ??= $availableRewindOptions[0];
        if (!in_array($rewindOption, $availableRewindOptions, true)) {
            throw new NotFoundHttpException('Not found');
        }

        $render = $this->cacheableRenderer->render(new CacheableContent(
            cacheability: Cacheability::for(
                cacheKey: sprintf('%s.%s', self::BASE_PATH, $rewindOption),
                cacheTags: RewindCacheTags::forOption($rewindOption),
            ),
            render: fn (): string => $this->renderFor($rewindOption),
        ));

        return new HtmlResponse(
            $this->appShell->render(
                content: $render->getContent() ?? '',
                navigationSection: NavigationSection::REWIND,
                openGraph: null,
            ),
            headers: $render->getCacheHeaders(),
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
}
