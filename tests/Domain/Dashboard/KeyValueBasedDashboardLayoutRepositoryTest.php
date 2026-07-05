<?php

namespace App\Tests\Domain\Dashboard;

use App\Domain\Dashboard\DashboardLayout;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Dashboard\KeyValueBasedDashboardLayoutRepository;
use App\Domain\Dashboard\Widget\WidgetName;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;

class KeyValueBasedDashboardLayoutRepositoryTest extends ContainerTestCase
{
    private KeyValueBasedDashboardLayoutRepository $repository;
    private KeyValueStore $keyValueStore;

    public function testFindWhenEmptyReturnsDefault(): void
    {
        $this->assertEquals(
            DashboardLayout::fromArray(DashboardLayout::default()),
            $this->repository->find(),
        );
    }

    public function testFindReturnsStoredLayout(): void
    {
        $layout = [
            ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 33],
            ['id' => 'dashboardWidget-b', 'widget' => 'weeklyStats', 'width' => 100],
        ];

        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::DASHBOARD,
            value: Value::fromString(Json::encode($layout)),
        ));

        $this->assertEquals(
            DashboardLayout::fromArray($layout),
            $this->repository->find(),
        );
    }

    public function testAddWidgetAppendsToTheLayout(): void
    {
        $layout = [
            ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 33],
            ['id' => 'dashboardWidget-b', 'widget' => 'weeklyStats', 'width' => 100],
        ];

        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::DASHBOARD,
            value: Value::fromString(Json::encode($layout)),
        ));

        $this->repository->addWidget(
            DashboardWidgetId::fromString('dashboardWidget-new'),
            WidgetName::fromConfigValue('eddington'),
            50,
        );

        $this->assertEquals(
            DashboardLayout::fromArray([
                ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 33],
                ['id' => 'dashboardWidget-b', 'widget' => 'weeklyStats', 'width' => 100],
                ['id' => 'dashboardWidget-new', 'widget' => 'eddington', 'width' => 50],
            ]),
            $this->repository->find(),
        );
    }

    public function testDeleteWidgetPreservesOrderOfRemainingWidgets(): void
    {
        $layout = [
            ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 33],
            ['id' => 'dashboardWidget-b', 'widget' => 'weeklyStats', 'width' => 100],
            ['id' => 'dashboardWidget-c', 'widget' => 'eddington', 'width' => 33],
        ];

        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::DASHBOARD,
            value: Value::fromString(Json::encode($layout)),
        ));

        $this->repository->deleteWidget(DashboardWidgetId::fromString('dashboardWidget-b'));

        $this->assertEquals(
            DashboardLayout::fromArray([
                ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 33],
                ['id' => 'dashboardWidget-c', 'widget' => 'eddington', 'width' => 33],
            ]),
            $this->repository->find(),
        );
    }

    public function testDeleteWidgetFromDefaultLayoutPersistsRemainder(): void
    {
        $this->repository->deleteWidget(DashboardWidgetId::fromString('dashboardWidget-introText'));

        $remaining = iterator_to_array($this->repository->find());
        $ids = array_column($remaining, 'id');

        $this->assertNotContains('dashboardWidget-introText', $ids);
        $this->assertContains('dashboardWidget-mostRecentActivities', $ids);
    }

    public function testUpdateWidgetConfigurationOnlyChangesTheMatchingWidget(): void
    {
        $layout = [
            ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 33],
            ['id' => 'dashboardWidget-b', 'widget' => 'streaks', 'width' => 33, 'config' => ['subtitle' => null, 'sportTypesToInclude' => []]],
            ['id' => 'dashboardWidget-c', 'widget' => 'eddington', 'width' => 33],
        ];

        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::DASHBOARD,
            value: Value::fromString(Json::encode($layout)),
        ));

        $this->repository->updateWidgetConfiguration(
            DashboardWidgetId::fromString('dashboardWidget-b'),
            ['subtitle' => 'Keep it up', 'sportTypesToInclude' => ['Run']],
        );

        $this->assertEquals(
            DashboardLayout::fromArray([
                ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 33],
                ['id' => 'dashboardWidget-b', 'widget' => 'streaks', 'width' => 33, 'config' => ['subtitle' => 'Keep it up', 'sportTypesToInclude' => ['Run']]],
                ['id' => 'dashboardWidget-c', 'widget' => 'eddington', 'width' => 33],
            ]),
            $this->repository->find(),
        );
    }

    public function testSaveLayoutReordersAppliesWidthsAndPreservesConfig(): void
    {
        $layout = [
            ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 33],
            ['id' => 'dashboardWidget-b', 'widget' => 'streaks', 'width' => 33, 'config' => ['subtitle' => 'Keep it up', 'sportTypesToInclude' => ['Run']]],
            ['id' => 'dashboardWidget-c', 'widget' => 'eddington', 'width' => 33],
        ];

        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::DASHBOARD,
            value: Value::fromString(Json::encode($layout)),
        ));

        $this->repository->saveLayout([
            ['id' => 'dashboardWidget-c', 'width' => 100],
            ['id' => 'dashboardWidget-a', 'width' => 50],
            ['id' => 'dashboardWidget-b', 'width' => 66],
        ]);

        $this->assertEquals(
            DashboardLayout::fromArray([
                ['id' => 'dashboardWidget-c', 'widget' => 'eddington', 'width' => 100],
                ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 50],
                ['id' => 'dashboardWidget-b', 'widget' => 'streaks', 'width' => 66, 'config' => ['subtitle' => 'Keep it up', 'sportTypesToInclude' => ['Run']]],
            ]),
            $this->repository->find(),
        );
    }

    public function testSaveLayoutIgnoresUnknownIdsAndAppendsStoredWidgetsMissingFromThePayload(): void
    {
        $layout = [
            ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 33],
            ['id' => 'dashboardWidget-b', 'widget' => 'weeklyStats', 'width' => 100],
            ['id' => 'dashboardWidget-c', 'widget' => 'eddington', 'width' => 33],
        ];

        $this->keyValueStore->save(KeyValue::fromState(
            key: Key::DASHBOARD,
            value: Value::fromString(Json::encode($layout)),
        ));

        $this->repository->saveLayout([
            ['id' => 'dashboardWidget-b', 'width' => 50],
            ['id' => 'dashboardWidget-unknown', 'width' => 66],
        ]);

        $this->assertEquals(
            DashboardLayout::fromArray([
                ['id' => 'dashboardWidget-b', 'widget' => 'weeklyStats', 'width' => 50],
                ['id' => 'dashboardWidget-a', 'widget' => 'introText', 'width' => 33],
                ['id' => 'dashboardWidget-c', 'widget' => 'eddington', 'width' => 33],
            ]),
            $this->repository->find(),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $this->repository = new KeyValueBasedDashboardLayoutRepository($this->keyValueStore);
    }
}
