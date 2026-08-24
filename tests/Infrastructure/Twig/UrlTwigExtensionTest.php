<?php

namespace App\Tests\Infrastructure\Twig;

use App\Application\AppUrl;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Image\ImageOrientation;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Twig\StringTwigExtension;
use App\Infrastructure\Twig\SvgsTwigExtension;
use App\Infrastructure\Twig\UrlTwigExtension;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Segment\SegmentBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UrlTwigExtensionTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private StringTwigExtension $stringTwigExtension;
    private SvgsTwigExtension $svgsTwigExtension;
    private UrlGeneratorInterface $urlGenerator;
    private RequestStack $requestStack;

    public function testToAbsoluteUrl(): void
    {
        $this->assertEquals(
            '/test/path',
            new UrlTwigExtension(
                appUrl: AppUrl::fromString('http://localhost:8081'),
                requestStack: $this->requestStack,
                urlGenerator: $this->urlGenerator,
                stringTwigExtension: $this->stringTwigExtension,
                svgsTwigExtension: $this->svgsTwigExtension,
            )->toRelativeUrl('test/path')
        );
        $this->assertEquals(
            '/test/path',
            new UrlTwigExtension(
                appUrl: AppUrl::fromString('http://localhost:8081'),
                requestStack: $this->requestStack,
                urlGenerator: $this->urlGenerator,
                stringTwigExtension: $this->stringTwigExtension,
                svgsTwigExtension: $this->svgsTwigExtension,
            )->toRelativeUrl('/test/path')
        );
        $this->assertEquals(
            '/base/test/path',
            new UrlTwigExtension(
                appUrl: AppUrl::fromString('http://localhost:8081/base/'),
                requestStack: $this->requestStack,
                urlGenerator: $this->urlGenerator,
                stringTwigExtension: $this->stringTwigExtension,
                svgsTwigExtension: $this->svgsTwigExtension,
            )->toRelativeUrl('test/path')
        );
        $this->assertEquals(
            '/base/test/path',
            new UrlTwigExtension(
                appUrl: AppUrl::fromString('http://localhost:8081/base/'),
                requestStack: $this->requestStack,
                urlGenerator: $this->urlGenerator,
                stringTwigExtension: $this->stringTwigExtension,
                svgsTwigExtension: $this->svgsTwigExtension,
            )->toRelativeUrl('/test/path')
        );
    }

    public function testPlaceholderImage(): void
    {
        $this->assertEquals(
            '/assets/placeholder.webp',
            new UrlTwigExtension(
                appUrl: AppUrl::fromString('http://localhost:8081'),
                requestStack: $this->requestStack,
                urlGenerator: $this->urlGenerator,
                stringTwigExtension: $this->stringTwigExtension,
                svgsTwigExtension: $this->svgsTwigExtension,
            )->placeholderImage()
        );

        $this->assertEquals(
            '/assets/placeholder-portrait.webp',
            new UrlTwigExtension(
                appUrl: AppUrl::fromString('http://localhost:8081'),
                requestStack: $this->requestStack,
                urlGenerator: $this->urlGenerator,
                stringTwigExtension: $this->stringTwigExtension,
                svgsTwigExtension: $this->svgsTwigExtension,
            )->placeholderImage(ImageOrientation::PORTRAIT)
        );
    }

    public function testSegmentLinkForVirtualSegments(): void
    {
        $extension = new UrlTwigExtension(
            appUrl: AppUrl::fromString('http://localhost:8081'),
            requestStack: $this->requestStack,
            urlGenerator: $this->urlGenerator,
            stringTwigExtension: $this->stringTwigExtension,
            svgsTwigExtension: $this->svgsTwigExtension,
        );

        $snapshot = [];
        foreach (['zwift', 'rouvy', 'mywhoosh', 'random'] as $deviceName) {
            $segment = SegmentBuilder::fromDefaults()
                ->withSportType(SportType::VIRTUAL_RIDE)
                ->withDeviceName($deviceName)
                ->build();
            $snapshot[$deviceName] = $extension->renderSegmentTitleLink($segment);
        }

        $this->assertMatchesJsonSnapshot(Json::encode($snapshot));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->stringTwigExtension = new StringTwigExtension();
        $this->svgsTwigExtension = new SvgsTwigExtension($this->getContainer()->get(KernelProjectDir::class));
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $this->requestStack = new RequestStack();
    }

    #[DataProvider('provideRelativeUrlsWithRedirectTo')]
    public function testToRelativeUrlWithRedirectTo(string $appUrl, string $path, string $redirectTo, string $expected): void
    {
        $this->assertEquals(
            $expected,
            $this->extension(AppUrl::fromString($appUrl))->toRelativeUrlWithRedirectTo($path, $redirectTo)
        );
    }

    /**
     * @return \Generator<string, array{string, string, string, string}>
     */
    public static function provideRelativeUrlsWithRedirectTo(): \Generator
    {
        yield 'plain paths' => [
            'http://localhost:8081',
            'admin/activities/activity-1/edit',
            'activities/activity-1',
            '/admin/activities/activity-1/edit?redirectTo=%2Factivities%2Factivity-1',
        ];
        yield 'both sides get the base path' => [
            'http://localhost:8081/base/',
            'admin/activities/activity-1/edit',
            'activities/activity-1',
            '/base/admin/activities/activity-1/edit?redirectTo=%2Fbase%2Factivities%2Factivity-1',
        ];
        yield 'a path that already carries a query string keeps it' => [
            'http://localhost:8081',
            'admin/activities?page=2',
            'activities/activity-1',
            '/admin/activities?page=2&redirectTo=%2Factivities%2Factivity-1',
        ];
        yield 'the redirect target is encoded' => [
            'http://localhost:8081',
            'admin/activities/activity-1/edit',
            'activities?filters[isCommute]=true',
            '/admin/activities/activity-1/edit?redirectTo=%2Factivities%3Ffilters%5BisCommute%5D%3Dtrue',
        ];
    }

    public function testToRedirectUrlWithoutARequest(): void
    {
        $this->assertEquals('/default', $this->extension()->toRedirectUrl('/default'));
    }

    #[DataProvider('provideRedirectToQueryParams')]
    public function testToRedirectUrl(?string $redirectTo, string $expected): void
    {
        $this->requestStack->push(new Request(query: is_null($redirectTo) ? [] : ['redirectTo' => $redirectTo]));

        $this->assertEquals($expected, $this->extension()->toRedirectUrl('/default'));
    }

    /**
     * @return \Generator<string, array{?string, string}>
     */
    public static function provideRedirectToQueryParams(): \Generator
    {
        yield 'no query param' => [null, '/default'];
        yield 'empty query param' => ['', '/default'];
        yield 'a path within the app' => ['/activities/activity-1', '/activities/activity-1'];
        yield 'protocol relative' => ['//evil.com', '/default'];
        yield 'absolute url' => ['https://evil.com', '/default'];
        yield 'javascript uri' => ['javascript:alert(1)', '/default'];
    }

    private function extension(?AppUrl $appUrl = null): UrlTwigExtension
    {
        return new UrlTwigExtension(
            appUrl: $appUrl ?? AppUrl::fromString('http://localhost:8081'),
            requestStack: $this->requestStack,
            urlGenerator: $this->urlGenerator,
            stringTwigExtension: $this->stringTwigExtension,
            svgsTwigExtension: $this->svgsTwigExtension,
        );
    }
}
