<?php

declare(strict_types=1);

namespace App\Domain\Challenge;

use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Http\Page\Page;
use App\Infrastructure\Http\Page\ProvidesCacheKeyFromPath;
use Twig\Environment;

final readonly class ChallengesPage implements Page
{
    use ProvidesCacheKeyFromPath;

    public function __construct(
        private ChallengeRepository $challengeRepository,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'challenges';
    }

    public function getCacheability(): Cacheability
    {
        // The month headings are rendered in the configured locale, hence the appearance tag.
        return Cacheability::for(
            cacheTags: CacheTags::of(CacheTag::CHALLENGES, CacheTag::SETTINGS_APPEARANCE),
        );
    }

    public function render(): string
    {
        $challengesGroupedByMonth = [];
        foreach ($this->challengeRepository->findAll() as $challenge) {
            $challengesGroupedByMonth[$challenge->getCreatedOn()->translatedFormat('F Y')][] = $challenge;
        }

        return $this->twig->load('html/challenges.html.twig')->render([
            'challengesGroupedPerMonth' => $challengesGroupedByMonth,
        ]);
    }
}
