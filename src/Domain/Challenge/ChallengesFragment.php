<?php

declare(strict_types=1);

namespace App\Domain\Challenge;

use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use Twig\Environment;

final readonly class ChallengesFragment implements Fragment
{
    public function __construct(
        private ChallengeRepository $challengeRepository,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'challenges';
    }

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(RootCacheTag::CHALLENGES),
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
