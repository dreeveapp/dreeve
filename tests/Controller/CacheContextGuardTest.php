<?php

namespace App\Tests\Controller;

use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContextRegistry;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\ProvideTestData;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class CacheContextGuardTest extends AdminWebTestCase
{
    use ProvideTestData;

    private const array URLS = [
        'activities' => '/activities',
        'activity' => '/activities/activity-9756441741',
        'badges' => '/badges',
        'best_efforts' => '/best-efforts',
        'best_efforts_history' => '/best-efforts/Ride/10000',
        'challenges' => '/challenges',
        'dashboard' => '/dashboard',
        'dashboard_power_output' => '/dashboard/power-output',
        'dashboard_training_load' => '/dashboard/training-load',
        'eddington' => '/eddington',
        'gear' => '/gear',
        'gear_maintenance' => '/gear/maintenance',
        'gear_recording_devices' => '/gear/recording-devices',
        'heatmap' => '/heatmap',
        'milestones' => '/milestones',
        'monthly_stats' => '/monthly-stats',
        'monthly_stats_month' => '/monthly-stats/2023-06',
        'photos' => '/photos',
        'rewind' => '/rewind/2023',
        'rewind_compare' => '/rewind/2023/compare/2022',
        'segment' => '/segments/segment-10',
        'segments' => '/segments',
        'activity_best_efforts' => '/api/internal/activities/activity-9542782314/best-efforts',
        'activity_coordinates' => '/api/internal/activities/activity-9756441741/coordinates',
        'activity_data_table' => '/api/internal/activities/data-table',
        'activity_metrics' => '/api/internal/activities/activity-9756441741/metrics',
        'activity_polylines' => '/api/internal/activities/activity-9830227112/polylines',
        'activity_route_matches' => '/api/internal/activities/activity-9542782314/route-matches',
        'activity_segments' => '/api/internal/activities/activity-9542782314/segments',
        'activity_shifting' => '/api/internal/activities/activity-9542782314/shifting',
        'dashboard_widget' => '/api/internal/dashboard/widget/dashboardWidget-introText',
        'gear_maintenance_due' => '/api/internal/gear/maintenance-due',
        'heatmap_countries' => '/api/internal/heatmap/countries',
        'heatmap_routes' => '/api/internal/heatmap/routes',
        'segment_data_table' => '/api/internal/segments/data-table',
        'segment_polylines' => '/api/internal/segments/segment-10/polylines',
        'badge_dreeve' => '/badge/dreeve.svg',
        'badge_personal_best' => '/badge/pb/ride.svg',
        'badge_zwift' => '/badge/zwift.svg',
    ];

    public function testEveryListedRouteServesARenderWithACacheKeyOfItsOwn(): void
    {
        $this->provideFullTestSet();
        $this->addSegmentWithAPolylineFixtures();

        $cacheKeys = [];
        foreach (self::URLS as $routeName => $url) {
            $this->client->request('GET', $url);

            $this->assertResponseIsSuccessful(sprintf('Route "%s" (%s) does not render.', $routeName, $url));
            $cacheKey = $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key');
            $this->assertNotNull($cacheKey, sprintf('Route "%s" does not go through the CacheableRenderer.', $routeName));

            $cacheKeys[$routeName] = $cacheKey;
        }

        $this->assertEquals(array_unique($cacheKeys), $cacheKeys);
    }

    public function testEveryRouteThatVariesPerVisitorIsNotStoredByTheBrowser(): void
    {
        $this->provideFullTestSet();
        $this->addSegmentWithAPolylineFixtures();

        foreach (self::URLS as $routeName => $url) {
            $this->client->request('GET', $url);

            if (!preg_match('/\.[a-z]+=[a-z]+/', (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'))) {
                continue;
            }

            $this->assertStringContainsString(
                'no-store',
                (string) $this->client->getResponse()->headers->get('Cache-Control'),
                sprintf('Route "%s" varies per visitor, but does not send "Cache-Control: private, no-store".', $routeName),
            );
        }
    }

    public function testEveryRouteThatVariesByAuthenticationHasAnotherCacheKeyOnceLoggedIn(): void
    {
        $this->provideFullTestSet();
        $this->addSegmentWithAPolylineFixtures();

        $anonymousCacheKeys = [];
        foreach (self::URLS as $routeName => $url) {
            $this->client->request('GET', $url);

            $cacheKey = (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key');
            if (!str_contains($cacheKey, AuthenticatedCacheContext::getKey().'=')) {
                continue;
            }

            $this->assertResponseHeaderSame('Cache-Control', 'max-age=0, must-revalidate, no-store, private', sprintf('Route "%s"', $routeName));
            $anonymousCacheKeys[$routeName] = $cacheKey;
        }

        $this->client->loginUser($this->adminUser());

        foreach ($anonymousCacheKeys as $routeName => $anonymousCacheKey) {
            $this->client->request('GET', self::URLS[$routeName]);

            $this->assertNotEquals(
                $anonymousCacheKey,
                $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
                sprintf('Route "%s" serves the same cache key to every visitor.', $routeName),
            );
        }

        $this->assertArrayHasKey('activity', $anonymousCacheKeys);
        $this->assertArrayHasKey('gear_maintenance', $anonymousCacheKeys);
    }

    public function testRoutesNotVaryingByAuthenticationRenderIdenticallyForEveryVisitor(): void
    {
        $this->provideFullTestSet();
        $this->addSegmentWithAPolylineFixtures();

        $renderedForAnonymousVisitor = [];
        foreach (self::URLS as $routeName => $url) {
            $this->client->request('GET', $url);

            if (str_contains((string) $this->client->getResponse()->headers->get('Cache-Control'), 'no-store')) {
                continue;
            }

            $renderedForAnonymousVisitor[$routeName] = (string) $this->client->getResponse()->getContent();
        }

        $this->getContainer()->get(RenderCache::class)->clear();
        $this->client->loginUser($this->adminUser());

        foreach ($renderedForAnonymousVisitor as $routeName => $rendered) {
            $this->client->request('GET', self::URLS[$routeName]);

            $this->assertEquals(
                $rendered,
                (string) $this->client->getResponse()->getContent(),
                sprintf(
                    'Route "%s" renders differently once you are logged in, but does not declare %s. Either declare the context or stop rendering logged-in-only markup.',
                    $routeName,
                    AuthenticatedCacheContext::class,
                )
            );
        }

        $this->assertNotEmpty($renderedForAnonymousVisitor);
    }

    public function testEveryCacheContextHasAKeyOfItsOwn(): void
    {
        /** @var CacheContextRegistry $cacheContextRegistry */
        $cacheContextRegistry = $this->getContainer()->get(CacheContextRegistry::class);

        $keys = [];
        foreach ($cacheContextRegistry->all() as $cacheContext) {
            $keys[] = $cacheContext::getKey();
        }

        $this->assertNotEmpty($keys);
        $this->assertEquals(array_unique($keys), $keys);
    }

    public function testTheAuthenticationContextResolvesToADifferentSegmentPerVisitor(): void
    {
        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->getContainer()->get('security.token_storage');
        /** @var AuthenticatedCacheContext $cacheContext */
        $cacheContext = $this->getContainer()->get(AuthenticatedCacheContext::class);

        $tokenStorage->setToken(null);
        $this->assertEquals('anon', $cacheContext->resolve());

        $tokenStorage->setToken(new UsernamePasswordToken(
            new InMemoryUser('admin', null, ['ROLE_ADMIN']),
            'main',
            ['ROLE_ADMIN'],
        ));
        $this->assertEquals('auth', $cacheContext->resolve());

        $tokenStorage->setToken(null);
    }
}
