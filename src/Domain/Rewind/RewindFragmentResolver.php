<?php

declare(strict_types=1);

namespace App\Domain\Rewind;

use App\Domain\Rewind\FindAvailableRewindOptions\FindAvailableRewindOptions;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Fragment\FragmentResolver;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use Twig\Environment;

final readonly class RewindFragmentResolver implements FragmentResolver
{
    private const string BASE_PATH = 'rewind';

    public function __construct(
        private QueryBus $queryBus,
        private RewindItemsBuilder $rewindItemsBuilder,
        private Environment $twig,
    ) {
    }

    public function resolve(string $path): ?ResolvedFragment
    {
        if (self::BASE_PATH !== $path && !str_starts_with($path, self::BASE_PATH.'/')) {
            return null;
        }

        $availableRewindOptions = $this->queryBus->ask(new FindAvailableRewindOptions())->getAvailableOptions();
        if ([] === $availableRewindOptions) {
            return null;
        }

        $rewindOption = self::BASE_PATH === $path
            ? $availableRewindOptions[0]
            : substr($path, strlen(self::BASE_PATH) + 1);

        if (!in_array($rewindOption, $availableRewindOptions, true)) {
            return null;
        }

        return new ResolvedFragment(
            path: self::BASE_PATH.'/'.$rewindOption,
            cacheability: $this->cacheabilityFor($rewindOption),
            render: fn (): string => $this->renderFor($rewindOption),
        );
    }

    private function cacheabilityFor(string $rewindOption): Cacheability
    {
        return Cacheability::for(
            cacheKey: sprintf('%s.%s', self::BASE_PATH, $rewindOption),
            cacheTags: RewindCacheTags::forOption($rewindOption),
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
