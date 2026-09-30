<?php

namespace App\Tests\Application;

use App\Application\AppShell;
use App\Application\Navigation\NavigationSection;
use App\Application\OpenGraph\OpenGraph;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Http\Fragment\ResolvedFragment;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class AppShellTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private AppShell $appShell;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->appShell->render(
            page: new ResolvedFragment(
                path: 'dashboard',
                cacheability: Cacheability::for('dashboard', CacheTags::empty()),
                render: fn (): null => null,
            ),
            content: '<p>The page content</p>',
        ));
    }

    public function testRenderMarksTheActiveSection(): void
    {
        $this->provideFullTestSet();

        $render = $this->appShell->render(
            page: new ResolvedFragment(
                path: 'activities',
                cacheability: Cacheability::for('activities', CacheTags::empty()),
                render: fn (): null => null,
                navigationSection: NavigationSection::ACTIVITIES,
            ),
            content: '',
        );

        $this->assertStringContainsString('href="/activities" aria-selected="true"', $render);
        $this->assertStringContainsString('href="/dashboard" aria-selected="false"', $render);
    }

    public function testRenderWithAnOpenGraph(): void
    {
        $this->provideFullTestSet();

        $render = $this->appShell->render(
            page: new ResolvedFragment(
                path: 'activities/activity-1',
                cacheability: Cacheability::for('activities/activity-1', CacheTags::empty()),
                render: fn (): null => null,
                openGraph: new OpenGraph(
                    path: 'activities/activity-1',
                    title: 'Morning Run',
                    description: 'Run · 10.00 km · 50:00 · 120 m',
                    imagePath: 'activities/activity-1/og-image.png',
                ),
            ),
            content: '',
        );

        $this->assertStringContainsString('<title>Morning Run | Dreeve</title>', $render);
        $this->assertStringContainsString('<meta property="og:title" content="Morning Run">', $render);
        $this->assertStringContainsString('<meta property="og:description" content="Run · 10.00 km · 50:00 · 120 m">', $render);
        $this->assertStringContainsString('<meta property="og:url" content="http://localhost:8080/activities/activity-1">', $render);
        $this->assertStringContainsString('<meta property="og:image" content="http://localhost:8080/activities/activity-1/og-image.png">', $render);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $render);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->appShell = $this->getContainer()->get(AppShell::class);
    }
}
