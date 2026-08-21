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
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UrlTwigExtensionTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private StringTwigExtension $stringTwigExtension;
    private SvgsTwigExtension $svgsTwigExtension;
    private UrlGeneratorInterface $urlGenerator;

    public function testToAbsoluteUrl(): void
    {
        $this->assertEquals(
            '/test/path',
            new UrlTwigExtension(
                appUrl: AppUrl::fromString('http://localhost:8081'),
                urlGenerator: $this->urlGenerator,
                stringTwigExtension: $this->stringTwigExtension,
                svgsTwigExtension: $this->svgsTwigExtension,
            )->toRelativeUrl('test/path')
        );
        $this->assertEquals(
            '/test/path',
            new UrlTwigExtension(
                appUrl: AppUrl::fromString('http://localhost:8081'),
                urlGenerator: $this->urlGenerator,
                stringTwigExtension: $this->stringTwigExtension,
                svgsTwigExtension: $this->svgsTwigExtension,
            )->toRelativeUrl('/test/path')
        );
        $this->assertEquals(
            '/base/test/path',
            new UrlTwigExtension(
                appUrl: AppUrl::fromString('http://localhost:8081/base/'),
                urlGenerator: $this->urlGenerator,
                stringTwigExtension: $this->stringTwigExtension,
                svgsTwigExtension: $this->svgsTwigExtension,
            )->toRelativeUrl('test/path')
        );
        $this->assertEquals(
            '/base/test/path',
            new UrlTwigExtension(
                appUrl: AppUrl::fromString('http://localhost:8081/base/'),
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
                urlGenerator: $this->urlGenerator,
                stringTwigExtension: $this->stringTwigExtension,
                svgsTwigExtension: $this->svgsTwigExtension,
            )->placeholderImage()
        );

        $this->assertEquals(
            '/assets/placeholder-portrait.webp',
            new UrlTwigExtension(
                appUrl: AppUrl::fromString('http://localhost:8081'),
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
    }
}
