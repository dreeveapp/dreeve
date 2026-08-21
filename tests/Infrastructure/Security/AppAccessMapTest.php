<?php

namespace App\Tests\Infrastructure\Security;

use App\Domain\Settings\SecuritySettings;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Security\AppAccessMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\PathRequestMatcher;
use Symfony\Component\Security\Http\AccessMap;

class AppAccessMapTest extends TestCase
{
    public function testItLetsTheDecoratedMapDecideFirst(): void
    {
        $accessMap = new AccessMap();
        $accessMap->add(new PathRequestMatcher('^/admin'), ['ROLE_ADMIN']);

        $appAccessMap = new AppAccessMap($accessMap, $this->settingsRepository(requiresAuthentication: true));

        $this->assertEquals([['ROLE_ADMIN'], null], $appAccessMap->getPatterns(Request::create('/admin/settings')));
    }

    public function testItRequiresNothingWhenAuthenticationIsNotRequired(): void
    {
        $appAccessMap = new AppAccessMap(new AccessMap(), $this->settingsRepository(requiresAuthentication: false));

        $this->assertEquals([null, null], $appAccessMap->getPatterns(Request::create('/dashboard')));
    }

    #[DataProvider('provideProtectedPaths')]
    public function testItRequiresAnAdminWhenAuthenticationIsRequired(string $path): void
    {
        $appAccessMap = new AppAccessMap(new AccessMap(), $this->settingsRepository(requiresAuthentication: true));

        $this->assertEquals([['ROLE_ADMIN'], null], $appAccessMap->getPatterns(Request::create($path)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideProtectedPaths(): iterable
    {
        yield 'dashboard' => ['/'];
        yield 'page' => ['/activities'];
        yield 'fragment api' => ['/api/internal/fragment/page/dashboard'];
        yield 'gpx api' => ['/api/internal/activity/activity-1/route.gpx'];
        yield 'images' => ['/files/gear/bike.png'];
        yield 'badge' => ['/badge/strava.svg'];
        yield 'ai chat' => ['/ai/chat'];
    }

    #[DataProvider('providePublicPaths')]
    public function testItKeepsPublicPathsOpenWhenAuthenticationIsRequired(string $path): void
    {
        $appAccessMap = new AppAccessMap(new AccessMap(), $this->settingsRepository(requiresAuthentication: true));

        $this->assertEquals([null, null], $appAccessMap->getPatterns(Request::create($path)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providePublicPaths(): iterable
    {
        yield 'strava webhook' => ['/strava/webhook'];
        yield 'strava oauth' => ['/strava-oauth'];
        yield 'finish setup' => ['/finish-setup'];
        yield 'manifest' => ['/manifest.json'];
        yield 'assets' => ['/assets/placeholder.webp'];
        yield 'css' => ['/css/dist/tailwind.min.css'];
        yield 'js' => ['/js/app.js'];
        yield 'libraries' => ['/libraries/leaflet/leaflet.js'];
    }

    public function testItDoesNotConfusePrefixesWithDirectories(): void
    {
        $appAccessMap = new AppAccessMap(new AccessMap(), $this->settingsRepository(requiresAuthentication: true));

        $this->assertEquals([['ROLE_ADMIN'], null], $appAccessMap->getPatterns(Request::create('/assets-overview')));
    }

    private function settingsRepository(bool $requiresAuthentication): SettingsRepository
    {
        $settingsRepository = $this->createStub(SettingsRepository::class);
        $settingsRepository->method('security')->willReturn(
            SecuritySettings::fromArray(['requiresAuthentication' => $requiresAuthentication])
        );

        return $settingsRepository;
    }
}
