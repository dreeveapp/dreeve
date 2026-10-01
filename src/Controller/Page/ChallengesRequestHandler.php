<?php

declare(strict_types=1);

namespace App\Controller\Page;

use App\Application\Navigation\NavigationSection;
use App\Application\PageRenderer;
use App\Domain\Challenge\ChallengeRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class ChallengesRequestHandler
{
    public function __construct(
        private ChallengeRepository $challengeRepository,
        private Environment $twig,
        private PageRenderer $pageRenderer,
    ) {
    }

    #[Route(path: '/challenges', name: 'challenges', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'challenges',
                cacheTags: CacheTags::of(RootCacheTag::CHALLENGES),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::CHALLENGES,
        );
    }

    private function renderFor(): string
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
