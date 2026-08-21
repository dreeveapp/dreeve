<?php

namespace App\Tests\Domain\Dashboard;

use App\Domain\Dashboard\DashboardLayout;
use App\Domain\Dashboard\Widget\ConfiguredWidgets;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class DashboardWidgetFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private string $snapshotName;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->saveLayoutContainingEveryAvailableWidget();

        foreach ($this->getContainer()->get(ConfiguredWidgets::class) as $configuredWidget) {
            $this->snapshotName = (string) $configuredWidget->getName();

            $this->client->request('GET', '/api/internal/fragment/partial/dashboard/widget/'.$configuredWidget->getId());

            $this->assertResponseIsSuccessful();
            $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
        }
    }

    public function testItDoesNotServeAWidgetThatIsNotOnTheLayout(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/fragment/partial/dashboard/widget/dashboardWidget-doesNotExist');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotServeAMalformedWidgetId(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/fragment/partial/dashboard/widget/doesNotExist');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItIsNotServedAsAPageFragment(): void
    {
        $this->provideFullTestSet();

        $this->client->request('GET', '/api/internal/fragment/page/dashboard/widget/dashboardWidget-introText');

        $this->assertResponseStatusCodeSame(404);
    }

    protected function getSnapshotId(): string
    {
        return new \ReflectionClass($this)->getShortName().'--'.
            $this->name().'--'.
            $this->snapshotName;
    }

    private function saveLayoutContainingEveryAvailableWidget(): void
    {
        $layout = DashboardLayout::default();
        $configuredWidgetNames = array_column($layout, 'widget');

        foreach (array_keys($this->getContainer()->get(ConfiguredWidgets::class)->getAvailableWidgets()) as $widgetName) {
            if (in_array($widgetName, $configuredWidgetNames, true)) {
                continue;
            }
            $layout[] = ['id' => 'dashboardWidget-'.$widgetName, 'widget' => $widgetName, 'width' => 100];
        }

        $this->getContainer()->get(KeyValueStore::class)->save(KeyValue::fromState(
            key: Key::DASHBOARD,
            value: Value::fromString(Json::encode($layout)),
        ));
    }
}
