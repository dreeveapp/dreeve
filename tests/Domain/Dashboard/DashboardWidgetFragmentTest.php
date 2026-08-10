<?php

namespace App\Tests\Domain\Dashboard;

use App\Domain\Dashboard\DashboardLayout;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;

class DashboardWidgetFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    #[DataProvider(methodName: 'provideDashboardWidgetId')]
    public function testRender(string $dashboardWidgetId): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/fragment/partial/dashboard/widget/'.$dashboardWidgetId);

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public static function provideDashboardWidgetId(): iterable
    {
        foreach (DashboardLayout::default() as $layoutItem) {
            yield $layoutItem['widget'] => [$layoutItem['id']];
        }
    }
}
