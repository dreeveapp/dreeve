<?php

declare(strict_types=1);

namespace App\Controller\Segment;

use App\Application\AppShell;
use App\Application\Countries;
use App\Application\Navigation\NavigationSection;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableContent;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[AsController]
final readonly class SegmentsRequestHandler
{
    public function __construct(
        private SegmentRepository $segmentRepository,
        private SportTypeRepository $sportTypeRepository,
        private Countries $countries,
        private Environment $twig,
        private CacheableRenderer $cacheableRenderer,
        private AppShell $appShell,
    ) {
    }

    #[Route(path: '/segments', name: 'segments', methods: ['GET'], priority: 3)]
    public function handle(): Response
    {
        $render = $this->cacheableRenderer->render(new CacheableContent(
            cacheability: Cacheability::for(
                cacheKey: 'segments',
                cacheTags: CacheTags::of(
                    RootCacheTag::SEGMENTS,
                    // The sport type filter only lists the sport types that were actually imported.
                    RootCacheTag::ACTIVITIES,
                ),
            ),
            render: fn (): string => $this->renderFor(),
        ));

        return new HtmlResponse(
            $this->appShell->render(
                content: $render->getContent() ?? '',
                navigationSection: NavigationSection::SEGMENTS,
                openGraph: null,
            ),
            headers: $render->getCacheHeaders(),
        );
    }

    private function renderFor(): string
    {
        return $this->twig->load('html/segment/segments.html.twig')->render([
            'sportTypes' => $this->sportTypeRepository->findAll(),
            'countries' => $this->countries->getUsedInSegments(),
            'totalSegmentCount' => $this->segmentRepository->count(),
        ]);
    }
}
