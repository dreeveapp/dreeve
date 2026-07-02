<?php

namespace App\Tests\Domain\Dashboard;

use App\Domain\Dashboard\DashboardLayout;
use App\Domain\Dashboard\KeyValueBasedDashboardLayoutRepository;
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
            ['widget' => 'introText', 'width' => 33, 'enabled' => true],
            ['widget' => 'weeklyStats', 'width' => 100, 'enabled' => false],
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

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $this->repository = new KeyValueBasedDashboardLayoutRepository($this->keyValueStore);
    }
}
