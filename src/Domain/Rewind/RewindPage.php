<?php

declare(strict_types=1);

namespace App\Domain\Rewind;

use App\Domain\Rewind\FindAvailableRewindOptions\FindAvailableRewindOptions;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Http\Page\PageWithParameters;
use Twig\Environment;

final readonly class RewindPage implements PageWithParameters
{
    private const string BASE_PATH = 'rewind';

    public function __construct(
        private QueryBus $queryBus,
        private RewindItemsBuilder $rewindItemsBuilder,
        private Environment $twig,
        private string $activeRewindOption = FindAvailableRewindOptions::ALL_TIME,
    ) {
    }

    public function resolve(string $path): ?self
    {
        $availableRewindOptions = $this->queryBus->ask(new FindAvailableRewindOptions())->getAvailableOptions();

        if (self::BASE_PATH === $path) {
            return clone ($this, ['activeRewindOption' => $availableRewindOptions[0]]);
        }

        if (!str_starts_with($path, self::BASE_PATH.'/')) {
            return null;
        }

        $requestedRewindOption = substr($path, strlen(self::BASE_PATH) + 1);
        if (!in_array($requestedRewindOption, $availableRewindOptions, true)) {
            return null;
        }

        return clone ($this, ['activeRewindOption' => $requestedRewindOption]);
    }

    public function getPath(): string
    {
        return self::BASE_PATH.'/'.$this->activeRewindOption;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: sprintf('%s.%s', self::BASE_PATH, $this->activeRewindOption),
            cacheTags: RewindCacheTags::forOption($this->activeRewindOption),
        );
    }

    public function render(): string
    {
        $availableRewindOptionsResponse = $this->queryBus->ask(new FindAvailableRewindOptions());

        return $this->twig->load('html/rewind/rewind.html.twig')->render([
            'availableRewindOptions' => $availableRewindOptionsResponse->getAvailableOptions(),
            'activeRewindOption' => $this->activeRewindOption,
            'rewindItems' => $this->rewindItemsBuilder->build(
                rewindOption: $this->activeRewindOption,
                yearsToQuery: $availableRewindOptionsResponse->getYearsToQuery($this->activeRewindOption),
            ),
            'isAllTimeRewind' => FindAvailableRewindOptions::ALL_TIME === $this->activeRewindOption,
        ]);
    }
}
