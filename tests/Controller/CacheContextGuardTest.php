<?php

namespace App\Tests\Controller;

use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContextRegistry;
use App\Infrastructure\Cache\Render\RenderCache;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\ProvideTestData;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class CacheContextGuardTest extends AdminWebTestCase
{
    use ProvideTestData;

    private const array PARAMS_PER_ROUTE = [
        'activity' => ['activityId' => 'activity-9756441741'],
        'activity_metrics' => ['activityId' => 'activity-9756441741'],
        'activity_coordinates' => ['activityId' => 'activity-9756441741'],
        'activity_polylines' => ['activityId' => 'activity-9830227112'],
        'activity_best_efforts' => ['activityId' => 'activity-9542782314'],
        'activity_segments' => ['activityId' => 'activity-9542782314'],
        'activity_route_matches' => ['activityId' => 'activity-9542782314'],
        'activity_shifting' => ['activityId' => 'activity-9542782314'],
        'segment' => ['segmentId' => 'segment-10'],
        'segment_polylines' => ['segmentId' => 'segment-10'],
        'best_efforts_history' => ['activityType' => 'Ride', 'distanceInMeter' => 10000],
        'badge_personal_best' => ['sportType' => 'ride'],
        'dashboard_widget' => ['dashboardWidgetId' => 'dashboardWidget-introText'],
        'monthly_stats_month' => ['month' => '2023-06'],
        'rewind' => ['rewindOption' => '2023'],
        'rewind_compare' => ['left' => '2023', 'right' => '2022'],
    ];

    private const array UNCACHED_ROUTES = [
        'activity_og_image',
        'api_activity_gpx',
        'ai_chat_sse',
        'finish_setup',
        'local_image',
        'manifest',
        'strava_oauth',
        'strava_webhook_challenge',
    ];

    public function testEveryPublicRouteServesARenderWithACacheKeyOfItsOwn(): void
    {
        $this->provideRouteTestSet();

        $cacheKeysPerController = [];
        foreach ($this->cachedRoutes() as $routeName => [$url, $controller]) {
            $this->client->request('GET', $url);

            $this->assertResponseIsSuccessful(sprintf('Route "%s" (%s) does not render.', $routeName, $url));
            $cacheKey = $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key');
            $this->assertNotNull($cacheKey, sprintf('Route "%s" does not go through the CacheableRenderer.', $routeName));

            $cacheKeysPerController[$controller] = $cacheKey;
        }

        $this->assertNotEmpty($cacheKeysPerController);
        $this->assertEquals(array_unique($cacheKeysPerController), $cacheKeysPerController);
    }

    public function testEveryRouteThatVariesPerVisitorIsNotStoredByTheBrowser(): void
    {
        $this->provideRouteTestSet();

        foreach ($this->cachedRoutes() as $routeName => [$url]) {
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

    public function testRoutesNotVaryingByAuthenticationRenderIdenticallyForEveryVisitor(): void
    {
        $this->provideRouteTestSet();

        $renderedForAnonymousVisitor = [];
        foreach ($this->cachedRoutes() as $routeName => [$url]) {
            $this->client->request('GET', $url);

            if (str_contains((string) $this->client->getResponse()->headers->get('Cache-Control'), 'no-store')) {
                continue;
            }

            $renderedForAnonymousVisitor[$routeName] = (string) $this->client->getResponse()->getContent();
        }

        $this->getContainer()->get(RenderCache::class)->clear();
        $this->client->loginUser($this->adminUser());

        foreach ($renderedForAnonymousVisitor as $routeName => $rendered) {
            $this->client->request('GET', $this->cachedRoutes()[$routeName][0]);

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

    private function provideRouteTestSet(): void
    {
        $this->provideFullTestSet();
        $this->addSegmentWithAPolylineFixtures();
        $this->getContainer()->get(DbalSettingsRepository::class)->saveGroup(SettingsGroup::INTEGRATIONS, [
            'ai' => [
                'enabled' => true,
                'enableUI' => true,
                'provider' => 'openAI',
                'configuration' => ['key' => 'my-key', 'model' => 'cool-model'],
            ],
        ]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    private function cachedRoutes(): array
    {
        /** @var RouterInterface $router */
        $router = $this->getContainer()->get(RouterInterface::class);

        $routes = [];
        foreach ($router->getRouteCollection() as $routeName => $route) {
            $controller = (string) $route->getDefault('_controller');
            if (!str_starts_with($controller, 'App\\Controller\\')
                || str_starts_with($route->getPath(), '/admin')
                || str_starts_with($route->getPath(), '/api/v1')
                || ([] !== $route->getMethods() && !in_array('GET', $route->getMethods(), true))
                || in_array($routeName, self::UNCACHED_ROUTES, true)) {
                continue;
            }

            $requiredParams = array_diff($route->compile()->getPathVariables(), array_keys($route->getDefaults()));
            $this->assertEmpty(
                array_diff($requiredParams, array_keys(self::PARAMS_PER_ROUTE[$routeName] ?? [])),
                sprintf('Add the parameters of route "%s" to %s::PARAMS_PER_ROUTE.', $routeName, self::class),
            );

            $routes[$routeName] = [$router->generate($routeName, self::PARAMS_PER_ROUTE[$routeName] ?? []), $controller];
        }

        return $routes;
    }
}
