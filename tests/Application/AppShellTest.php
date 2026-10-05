<?php

namespace App\Tests\Application;

use App\Application\AppShell;
use App\Application\OpenGraph\OpenGraph;
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
            content: '<p>The page content</p>',
            navigationSection: null,
            openGraph: null,
        ));
    }

    public function testRenderWithAnOpenGraph(): void
    {
        $this->provideFullTestSet();

        $render = $this->appShell->render(
            content: '',
            navigationSection: null,
            openGraph: new OpenGraph(
                path: 'activities/activity-1',
                title: 'Morning Run',
                description: 'Run · 10.00 km · 50:00 · 120 m',
                imagePath: 'activities/activity-1/og-image.png',
            ),
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
