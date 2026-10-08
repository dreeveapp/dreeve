<?php

namespace App\Tests\Controller;

use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

class PrefixedIdentifierRouteTest extends ContainerTestCase
{
    #[DataProvider('provideRoutes')]
    public function testItOnlyMatchesPrefixedIdentifiers(string $routeName, string $prefixedPath, string $unprefixedPath): void
    {
        $router = $this->getContainer()->get(RouterInterface::class);

        $this->assertSame($routeName, $router->match($prefixedPath)['_route']);

        try {
            $this->assertNotSame($routeName, $router->match($unprefixedPath)['_route']);
        } catch (ResourceNotFoundException) {
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideRoutes(): iterable
    {
        yield 'activity' => ['activity', '/activities/activity-1', '/activities/1'];
        yield 'admin activity route' => ['admin_activity_route', '/admin/activities/activity-1/route', '/admin/activities/1/route'];
        yield 'activity og image' => ['activity_og_image', '/activities/activity-1/og-image.png', '/activities/1/og-image.png'];
        yield 'activity best efforts' => ['activity_best_efforts', '/api/internal/activities/activity-1/best-efforts', '/api/internal/activities/1/best-efforts'];
        yield 'activity coordinates' => ['activity_coordinates', '/api/internal/activities/activity-1/coordinates', '/api/internal/activities/1/coordinates'];
        yield 'activity metrics' => ['activity_metrics', '/api/internal/activities/activity-1/metrics', '/api/internal/activities/1/metrics'];
        yield 'activity polylines' => ['activity_polylines', '/api/internal/activities/activity-1/polylines', '/api/internal/activities/1/polylines'];
        yield 'activity route matches' => ['activity_route_matches', '/api/internal/activities/activity-1/route-matches', '/api/internal/activities/1/route-matches'];
        yield 'activity segments' => ['activity_segments', '/api/internal/activities/activity-1/segments', '/api/internal/activities/1/segments'];
        yield 'activity shifting' => ['activity_shifting', '/api/internal/activities/activity-1/shifting', '/api/internal/activities/1/shifting'];
        yield 'dashboard widget' => ['dashboard_widget', '/api/internal/dashboard/widget/dashboardWidget-introText', '/api/internal/dashboard/widget/introText'];
        yield 'segment' => ['segment', '/segments/segment-1', '/segments/1'];
        yield 'segment polylines' => ['segment_polylines', '/api/internal/segments/segment-1/polylines', '/api/internal/segments/1/polylines'];
    }
}
