<?php

declare(strict_types=1);

namespace App\Domain\Rewind;

use App\Domain\Rewind\FindAvailableRewindOptions\FindAvailableRewindOptions;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Page\DynamicPage;
use Twig\Environment;

final readonly class RewindComparePage implements DynamicPage
{
    private const string PATH_PATTERN = '#^rewind/(?<left>[^/]+)/compare(?:/(?<right>[^/]+))?$#';

    public function __construct(
        private QueryBus $queryBus,
        private RewindItemsBuilder $rewindItemsBuilder,
        private Environment $twig,
        private string $activeRewindOptionLeft = FindAvailableRewindOptions::ALL_TIME,
        private string $activeRewindOptionRight = FindAvailableRewindOptions::ALL_TIME,
    ) {
    }

    public function resolve(string $path): ?self
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

        return clone ($this, [
            'activeRewindOptionLeft' => $left,
            'activeRewindOptionRight' => $right,
        ]);
    }

    public function getPath(): string
    {
        return sprintf('rewind/%s/compare/%s', $this->activeRewindOptionLeft, $this->activeRewindOptionRight);
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: sprintf('rewind.%s.compare.%s', $this->activeRewindOptionLeft, $this->activeRewindOptionRight),
            // Both sides are rendered, so a change to either one of them invalidates this page.
            cacheTags: RewindCacheTags::forOption($this->activeRewindOptionLeft)
                ->merge(RewindCacheTags::forOption($this->activeRewindOptionRight)),
        );
    }

    public function render(): string
    {
        $availableRewindOptionsResponse = $this->queryBus->ask(new FindAvailableRewindOptions());
        $availableRewindOptions = $availableRewindOptionsResponse->getAvailableOptions();

        return $this->twig->load('html/rewind/rewind-compare.html.twig')->render([
            'availableRewindOptions' => $availableRewindOptions,
            'availableRewindOptionsToCompareWith' => array_filter(
                $availableRewindOptions,
                fn (string $option): bool => $option !== $this->activeRewindOptionLeft && $option !== $this->activeRewindOptionRight,
            ),
            'activeRewindOptionLeft' => $this->activeRewindOptionLeft,
            'activeRewindOptionRight' => $this->activeRewindOptionRight,
            'rewindItemsLeft' => $this->rewindItemsBuilder->build(
                rewindOption: $this->activeRewindOptionLeft,
                yearsToQuery: $availableRewindOptionsResponse->getYearsToQuery($this->activeRewindOptionLeft),
            ),
            'rewindItemsRight' => $this->rewindItemsBuilder->build(
                rewindOption: $this->activeRewindOptionRight,
                yearsToQuery: $availableRewindOptionsResponse->getYearsToQuery($this->activeRewindOptionRight),
            ),
            'rewindItemsLeftIsAllTimeRewind' => FindAvailableRewindOptions::ALL_TIME === $this->activeRewindOptionLeft,
            'rewindItemsRightIsAllTimeRewind' => FindAvailableRewindOptions::ALL_TIME === $this->activeRewindOptionRight,
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
