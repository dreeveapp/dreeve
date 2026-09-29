<?php

namespace App\Tests\Application;

use App\Application\IndexPage;
use App\Application\Navigation\NavigationSection;
use App\Application\OpenGraph\OpenGraph;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class IndexPageTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private IndexPage $indexPage;

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $this->assertMatchesHtmlSnapshot($this->indexPage->render('<p>The page content</p>', null));
    }

    public function testRenderMarksTheActiveSection(): void
    {
        $this->provideFullTestSet();

        $render = $this->indexPage->render('', NavigationSection::ACTIVITIES);

        $this->assertStringContainsString('href="/activities" aria-selected="true"', $render);
        $this->assertStringContainsString('href="/dashboard" aria-selected="false"', $render);
    }

    public function testRenderWithAnOpenGraph(): void
    {
        $this->provideFullTestSet();

        $render = $this->indexPage->render('', null, new OpenGraph(
            path: 'activities/activity-1',
            title: 'Morning Run',
            description: 'Run · 10.00 km · 50:00 · 120 m',
            imagePath: 'activities/activity-1/og-image.png',
        ));

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

        $this->indexPage = $this->getContainer()->get(IndexPage::class);
    }
}
