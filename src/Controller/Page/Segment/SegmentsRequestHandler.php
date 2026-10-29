<?php

declare(strict_types=1);

namespace App\Controller\Page\Segment;

use App\Application\Countries;
use App\Application\Navigation\NavigationSection;
use App\Application\OpenGraph\OpenGraph;
use App\Application\PageRenderer;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
final readonly class SegmentsRequestHandler
{
    public function __construct(
        private SegmentRepository $segmentRepository,
        private SportTypeRepository $sportTypeRepository,
        private Countries $countries,
        private Environment $twig,
        private PageRenderer $pageRenderer,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/segments', name: 'segments', methods: ['GET'])]
    public function handle(): HtmlResponse
    {
        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: 'segments',
                cacheTags: CacheTags::of(
                    RootCacheTag::SEGMENTS,
                    // The sport type filter only lists the sport types that were actually imported.
                    RootCacheTag::ACTIVITIES,
                ),
            ),
            render: fn (): string => $this->renderFor(),
            navigationSection: NavigationSection::SEGMENTS,
            openGraph: new OpenGraph(
                path: $this->urlGenerator->generate('segments'),
                title: $this->translator->trans('Segments'),
                description: $this->translator->trans('All segments and the efforts on them'),
            ),
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
