<?php

declare(strict_types=1);

namespace App\Domain\Segment;

use App\Application\Countries;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use Twig\Environment;

final readonly class SegmentsFragment implements Fragment
{
    public function __construct(
        private SegmentRepository $segmentRepository,
        private SportTypeRepository $sportTypeRepository,
        private Countries $countries,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'segments';
    }

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(
                RootCacheTag::SEGMENTS,
                // The sport type filter only lists the sport types that were actually imported.
                RootCacheTag::ACTIVITIES,
            ),
        );
    }

    public function render(): string
    {
        return $this->twig->load('html/segment/segments.html.twig')->render([
            'sportTypes' => $this->sportTypeRepository->findAll(),
            'countries' => $this->countries->getUsedInSegments(),
            'totalSegmentCount' => $this->segmentRepository->count(),
        ]);
    }
}
