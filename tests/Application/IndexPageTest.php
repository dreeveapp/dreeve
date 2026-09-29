<?php

namespace App\Tests\Application;

use App\Application\IndexPage;
use App\Application\Navigation\NavigationSection;
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

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->indexPage = $this->getContainer()->get(IndexPage::class);
    }
}
