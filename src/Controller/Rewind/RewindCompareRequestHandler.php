<?php

declare(strict_types=1);

namespace App\Controller\Rewind;

use App\Application\AppShell;
use App\Application\Navigation\NavigationSection;
use App\Domain\Rewind\FindAvailableRewindOptions\FindAvailableRewindOptions;
use App\Domain\Rewind\RewindCacheTags;
use App\Domain\Rewind\RewindItemsBuilder;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class RewindCompareRequestHandler
{
    public function __construct(
        private QueryBus $queryBus,
        private RewindItemsBuilder $rewindItemsBuilder,
        private CacheableRenderer $cacheableRenderer,
        private AppShell $appShell,
        private Environment $twig,
    ) {
    }

    #[Route(path: '/rewind/{left}/compare/{right}', name: 'rewind_compare', requirements: ['left' => '[^/]+', 'right' => '[^/]+'], defaults: ['right' => null], methods: ['GET'])]
    public function handle(string $left, ?string $right): Response
    {
        $availableRewindOptions = $this->queryBus->ask(new FindAvailableRewindOptions())->getAvailableOptions();
        if (count($availableRewindOptions) <= 2) {
            // "All time" and one other year are the only options. No need to compare rewinds.
            throw new NotFoundHttpException('Not found');
        }

        if (!in_array($left, $availableRewindOptions, true)) {
            throw new NotFoundHttpException('Not found');
        }

        $right ??= $availableRewindOptions[0] !== $left ? $availableRewindOptions[0] : $availableRewindOptions[1];
        if ($left === $right || !in_array($right, $availableRewindOptions, true)) {
            throw new NotFoundHttpException('Not found');
        }

        $render = $this->cacheableRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: sprintf('rewind.%s.compare.%s', $left, $right),
                // Both sides are rendered, so a change to either one of them invalidates this page.
                cacheTags: RewindCacheTags::forOption($left)->merge(RewindCacheTags::forOption($right)),
            ),
            render: fn (): string => $this->renderFor($left, $right),
        );

        return new HtmlResponse(
            $this->appShell->render(
                content: $render->getContent() ?? '',
                navigationSection: NavigationSection::REWIND,
                openGraph: null,
            ),
            headers: $render->getCacheHeaders(),
        );
    }

    private function renderFor(string $left, string $right): string
    {
        $availableRewindOptionsResponse = $this->queryBus->ask(new FindAvailableRewindOptions());
        $availableRewindOptions = $availableRewindOptionsResponse->getAvailableOptions();

        return $this->twig->load('html/rewind/rewind-compare.html.twig')->render([
            'availableRewindOptions' => $availableRewindOptions,
            'availableRewindOptionsToCompareWith' => array_filter(
                $availableRewindOptions,
                fn (string $option): bool => $option !== $left && $option !== $right,
            ),
            'activeRewindOptionLeft' => $left,
            'activeRewindOptionRight' => $right,
            'rewindItemsLeft' => $this->rewindItemsBuilder->build(
                rewindOption: $left,
                yearsToQuery: $availableRewindOptionsResponse->getYearsToQuery($left),
            ),
            'rewindItemsRight' => $this->rewindItemsBuilder->build(
                rewindOption: $right,
                yearsToQuery: $availableRewindOptionsResponse->getYearsToQuery($right),
            ),
            'rewindItemsLeftIsAllTimeRewind' => FindAvailableRewindOptions::ALL_TIME === $left,
            'rewindItemsRightIsAllTimeRewind' => FindAvailableRewindOptions::ALL_TIME === $right,
        ]);
    }
}
