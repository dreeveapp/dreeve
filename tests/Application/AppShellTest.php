<?php

namespace App\Tests\Application;

use App\Application\AppShell;
use App\Application\PageMetadata\PageMetadata;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatableMessage;

class AppShellTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private AppShell $appShell;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->getContainer()->get(RequestStack::class)->push(new Request(attributes: [
            '_route' => 'activity',
            '_route_params' => ['activityId' => 'activity-1'],
        ]));

        $render = $this->appShell->render(
            content: '<p>The page content</p>',
            navigationSection: null,
            metadata: new PageMetadata(
                title: 'Morning Run',
                description: new TranslatableMessage('A look back at {year}', ['{year}' => '2023']),
                imagePath: '/activities/activity-1/og-image.png',
            ),
        );

        $this->assertStringContainsString('<title>Morning Run | Dreeve</title>', $render);
        $this->assertStringContainsString('<meta property="og:title" content="Morning Run">', $render);
        $this->assertStringContainsString('<meta property="og:description" content="A look back at 2023">', $render);
        $this->assertStringContainsString('<meta property="og:url" content="http://localhost:8080/activities/activity-1">', $render);
        $this->assertStringContainsString('<meta property="og:image" content="http://localhost:8080/activities/activity-1/og-image.png">', $render);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $render);
        $this->assertMatchesHtmlSnapshot($render);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->appShell = $this->getContainer()->get(AppShell::class);
    }
}
