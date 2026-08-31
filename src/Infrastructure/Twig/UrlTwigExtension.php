<?php

declare(strict_types=1);

namespace App\Infrastructure\Twig;

use App\Application\AppUrl;
use App\Domain\Activity\Activity;
use App\Domain\Activity\ActivityFragmentPath;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Image\ImageOrientation;
use App\Domain\Segment\Segment;
use App\Domain\Segment\SegmentFragmentPath;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Http\Request\RedirectTo;
use App\Infrastructure\ValueObject\String\FilteredUrl;
use App\Infrastructure\ValueObject\String\RelativeUrl;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final readonly class UrlTwigExtension
{
    public function __construct(
        private AppUrl $appUrl,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
        private StringTwigExtension $stringTwigExtension,
        private SvgsTwigExtension $svgsTwigExtension,
    ) {
    }

    #[AsTwigFunction('relativeUrl')]
    public function toRelativeUrl(string $path): string
    {
        return RelativeUrl::from($path, $this->appUrl)->toRelativeUrl();
    }

    #[AsTwigFunction('relativeUrlWithRedirectTo')]
    public function toRelativeUrlWithRedirectTo(string $path, string $redirectTo): string
    {
        $url = RelativeUrl::from($path, $this->appUrl)->toRelativeUrl();

        return $url
            .(str_contains($url, '?') ? '&' : '?')
            .RedirectTo::QUERY_PARAM
            .'='.rawurlencode(RelativeUrl::from($redirectTo, $this->appUrl)->toRelativeUrl());
    }

    #[AsTwigFunction('redirectUrl')]
    public function toRedirectUrl(string $default): string
    {
        if (!($request = $this->requestStack->getCurrentRequest()) instanceof \Symfony\Component\HttpFoundation\Request) {
            return $default;
        }
        $redirectTo = RedirectTo::fromRequest($request, $this->appUrl);

        return $redirectTo instanceof RedirectTo ? (string) $redirectTo : $default;
    }

    /**
     * @param array<string, mixed> $filters
     */
    #[AsTwigFunction('filteredUrl')]
    public function toFilteredUrl(string $path, array $filters): string
    {
        return FilteredUrl::from($path, $filters, $this->appUrl)->toRelativeUrl();
    }

    #[AsTwigFunction('fragmentDataUrl')]
    public function toFragmentDataUrl(string $path): string
    {
        return $this->toRelativeUrl($this->urlGenerator->generate('api_fragment', [
            'type' => FragmentType::DATA->value,
            'path' => $path,
        ]));
    }

    #[AsTwigFunction('fragmentPartialUrl')]
    public function toFragmentPartialUrl(string $path): string
    {
        return $this->toRelativeUrl($this->urlGenerator->generate('api_fragment', [
            'type' => FragmentType::PARTIAL->value,
            'path' => $path,
        ]));
    }

    #[AsTwigFunction('activityFragmentPath')]
    public function activityFragmentPath(ActivityId $activityId, ?string $subResource = null): string
    {
        return ActivityFragmentPath::for($activityId, $subResource);
    }

    #[AsTwigFunction('placeholderImage')]
    public function placeholderImage(?ImageOrientation $imageOrientation = null): string
    {
        if (ImageOrientation::PORTRAIT === $imageOrientation) {
            return $this->toRelativeUrl('/assets/placeholder-portrait.webp');
        }

        return $this->toRelativeUrl('/assets/placeholder.webp');
    }

    #[AsTwigFilter('countryIcon')]
    public function countryIcon(string $countryCode): string
    {
        return $this->toRelativeUrl('/assets/images/flags/'.strtolower($countryCode).'.svg');
    }

    #[AsTwigFilter('activityLink', isSafe: ['html'])]
    public function renderActivityTitleLink(Activity $activity, ?int $ellipses = null, bool $truncate = false): string
    {
        $activityIcon = match (true) {
            !$activity->getSportType()->isVirtualRide() => $this->svgsTwigExtension->svgSportType($activity->getSportType()),
            $activity->isZwiftRide() => $this->svgsTwigExtension->svg('zwift-logo'),
            $activity->isRouvyRide() => $this->svgsTwigExtension->svg('rouvy-logo'),
            $activity->isMyWhooshRide() => $this->svgsTwigExtension->svg('my-whoosh-logo'),
            default => $this->svgsTwigExtension->svgSportType(SportType::RIDE),
        };

        $activityTitle = $activity->getName();

        return sprintf(
            '<a href="%s" data-router-link class="flex items-center gap-x-1 font-medium text-blue-600 hover:underline">%s<span class="%s">%s</span></a>',
            $this->toRelativeUrl(ActivityFragmentPath::for($activity->getId())),
            $activityIcon,
            $truncate ? 'truncate' : '',
            $ellipses ? $this->stringTwigExtension->doEllipses($activityTitle, $ellipses) : $activityTitle
        );
    }

    #[AsTwigFilter('segmentLink', isSafe: ['html'])]
    public function renderSegmentTitleLink(Segment $segment): string
    {
        $segmentIcon = match (true) {
            !$segment->getSportType()->isVirtualRide() => $this->svgsTwigExtension->svgSportType($segment->getSportType()),
            $segment->isZwiftSegment() => $this->svgsTwigExtension->svg('zwift-logo'),
            $segment->isRouvySegment() => $this->svgsTwigExtension->svg('rouvy-logo'),
            $segment->isMyWhooshSegment() => $this->svgsTwigExtension->svg('my-whoosh-logo'),
            default => $this->svgsTwigExtension->svgSportType(SportType::RIDE),
        };

        $segmentTitle = $segment->getName();

        return sprintf(
            '<a href="%s" data-router-link class="flex items-center gap-x-1 font-medium text-blue-600 hover:underline">%s<span class="truncate">%s</span></a>',
            $this->toRelativeUrl(SegmentFragmentPath::for($segment->getId())),
            $segmentIcon,
            $this->stringTwigExtension->doEllipses((string) $segmentTitle, 50)
        );
    }
}
