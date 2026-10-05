<?php

namespace App\Tests\Infrastructure\Twig;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\Twig\SvgsTwigExtension;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\ContainerTestCase;

class SvgsTwigExtensionTest extends ContainerTestCase
{
    private SvgsTwigExtension $extension;

    public function testInvalidSvg(): void
    {
        $this->expectExceptionObject(new \RuntimeException('No svg icon found for "invalid"'));
        $this->extension->svg('invalid');
    }

    public function testWithCustomParams(): void
    {
        $this->assertEquals(
            '<svg class="shrink-0 text-primary w-100" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" d="M11.083 5.104c.35-.8 1.485-.8 1.834 0l1.752 4.022a1 1 0 0 0 .84.597l4.463.342c.9.069 1.255 1.2.556 1.771l-3.33 2.723a1 1 0 0 0-.337 1.016l1.03 4.119c.214.858-.71 1.552-1.474 1.106l-3.913-2.281a1 1 0 0 0-1.008 0L7.583 20.8c-.764.446-1.688-.248-1.474-1.106l1.03-4.119A1 1 0 0 0 6.8 14.56l-3.33-2.723c-.698-.571-.342-1.702.557-1.771l4.462-.342a1 1 0 0 0 .84-.597l1.753-4.022Z"/></svg>',
            $this->extension->svg(
                name: 'star',
                size: 'w-100'
            ),
        );

        $this->assertEquals(
            '<svg class="shrink-0 w-100 text-yellow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><path fill="currentColor" d="M30.866 26.667h-4.49L42.565 0H6.615C2.961 0 0 2.985 0 6.667s2.962 6.666 6.615 6.666h4.469L.955 30.018S0 31.421 0 33.443C0 37.065 2.913 40 6.506 40h24.36c3.654 0 6.615-2.985 6.615-6.667 0-3.681-2.961-6.666-6.615-6.666"></path></svg>',
            $this->extension->svg(
                name: 'zwift-logo',
                size: 'w-100',
                iconColor: 'text-yellow'
            ),
        );
    }

    public function testSportTypeSvgs(): void
    {
        foreach (SportType::cases() as $sportType) {
            $this->assertStringStartsWith('<svg class="h-4 shrink-0" ', $this->extension->svgSportType($sportType), $sportType->value);
        }
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->extension = new SvgsTwigExtension($this->getContainer()->get(KernelProjectDir::class));
    }
}
