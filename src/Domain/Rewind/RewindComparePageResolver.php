<?php

declare(strict_types=1);

namespace App\Domain\Rewind;

use App\Domain\Rewind\FindAvailableRewindOptions\FindAvailableRewindOptions;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Page\PageResolver;
use App\Infrastructure\Http\Page\ResolvedPage;
use Twig\Environment;

final readonly class RewindComparePageResolver implements PageResolver
{
    private const string PATH_PATTERN = '#^rewind/(?<left>[^/]+)/compare(?:/(?<right>[^/]+))?$#';

    public function __construct(
        private QueryBus $queryBus,
        private RewindItemsBuilder $rewindItemsBuilder,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedPage
    {
        if (!preg_match(self::PATH_PATTERN, $path, $matches)) {
            return null;
        }

        $availableRewindOptions = $this->queryBus->ask(new FindAvailableRewindOptions())->getAvailableOptions();
        if (count($availableRewindOptions) <= 2) {
            // "All time" and one other year are the only options. No need to compare rewinds.
            return null;
        }

        $left = $matches['left'];
        if (!in_array($left, $availableRewindOptions, true)) {
            return null;
        }

        $right = $matches['right'] ?? $this->defaultRewindOptionToCompareWith($left, $availableRewindOptions);
        if ($left === $right || !in_array($right, $availableRewindOptions, true)) {
            return null;
        }

        return new ResolvedPage(
            path: sprintf('rewind/%s/compare/%s', $left, $right),
            cacheability: $this->cacheabilityFor($left, $right),
            render: fn (): string => $this->renderFor($left, $right),
        );
    }

    private function cacheabilityFor(string $left, string $right): Cacheability
    {
        return Cacheability::for(
            cacheKey: sprintf('rewind.%s.compare.%s', $left, $right),
            // Both sides are rendered, so a change to either one of them invalidates this page.
            cacheTags: RewindCacheTags::forOption($left)->merge(RewindCacheTags::forOption($right)),
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

    /**
     * @param string[] $availableRewindOptions
     */
    private function defaultRewindOptionToCompareWith(string $left, array $availableRewindOptions): string
    {
        return $availableRewindOptions[0] !== $left ? $availableRewindOptions[0] : $availableRewindOptions[1];
    }
}
