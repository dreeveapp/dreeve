<?php

declare(strict_types=1);

namespace App\Tests\Domain\Dashboard\ConfigureWidget;

use App\Domain\Dashboard\ConfigureWidget\ConfigureWidget;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\CouldNotProcessCommand;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;

class ConfigureWidgetCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private KeyValueStore $keyValueStore;

    public function testItStoresTheSubmittedConfiguration(): void
    {
        $this->seedLayout([
            ['id' => 'dashboardWidget-streaks', 'widget' => 'streaks', 'width' => 33, 'config' => ['subtitle' => null, 'sportTypesToInclude' => []]],
        ]);

        $this->commandBus->dispatch(ConfigureWidget::fromPayload([
            'dashboardWidgetId' => 'dashboardWidget-streaks',
            'config' => ['subtitle' => 'Keep it up', 'sportTypesToInclude' => ['Run', 'Ride']],
        ]));

        $this->assertEqualsCanonicalizing(
            ['subtitle' => 'Keep it up', 'sportTypesToInclude' => ['Run', 'Ride']],
            $this->storedConfigFor('dashboardWidget-streaks'),
        );
    }

    public function testItCoercesValuesToTheDefaultTypeAndDropsUnknownKeys(): void
    {
        $this->seedLayout([
            ['id' => 'dashboardWidget-mostRecentActivities', 'widget' => 'mostRecentActivities', 'width' => 66, 'config' => ['numberOfActivitiesToDisplay' => 5]],
        ]);

        $this->commandBus->dispatch(ConfigureWidget::fromPayload([
            'dashboardWidgetId' => 'dashboardWidget-mostRecentActivities',
            'config' => ['numberOfActivitiesToDisplay' => '8', 'unknownKey' => 'ignored'],
        ]));

        $this->assertSame(
            ['numberOfActivitiesToDisplay' => 8],
            $this->storedConfigFor('dashboardWidget-mostRecentActivities'),
        );
    }

    public function testItClearsAMultiSelectWhenNothingIsSubmitted(): void
    {
        $this->seedLayout([
            ['id' => 'dashboardWidget-streaks', 'widget' => 'streaks', 'width' => 33, 'config' => ['subtitle' => 'x', 'sportTypesToInclude' => ['Run']]],
        ]);

        $this->commandBus->dispatch(ConfigureWidget::fromPayload([
            'dashboardWidgetId' => 'dashboardWidget-streaks',
            'config' => ['subtitle' => ''],
        ]));

        $this->assertEqualsCanonicalizing(
            ['subtitle' => null, 'sportTypesToInclude' => []],
            $this->storedConfigFor('dashboardWidget-streaks'),
        );
    }

    public function testItStoresNestedTrainingGoalsPreservingPeriodKeys(): void
    {
        $this->seedLayout([
            ['id' => 'dashboardWidget-trainingGoals', 'widget' => 'trainingGoals', 'width' => 33, 'config' => ['goals' => []]],
        ]);

        $goals = [
            'weekly' => [
                ['label' => 'Ride 100km', 'type' => 'distance', 'unit' => 'km', 'goal' => '100', 'sportTypesToInclude' => ['Ride']],
            ],
            'monthly' => [
                ['label' => 'Run 50km', 'type' => 'distance', 'unit' => 'km', 'goal' => '50', 'sportTypesToInclude' => ['Run']],
            ],
        ];

        $this->commandBus->dispatch(ConfigureWidget::fromPayload([
            'dashboardWidgetId' => 'dashboardWidget-trainingGoals',
            'config' => ['goals' => $goals],
        ]));

        $this->assertSame(['goals' => $goals], $this->storedConfigFor('dashboardWidget-trainingGoals'));
    }

    public function testItRejectsInvalidTrainingGoalsWithACleanMessage(): void
    {
        $this->seedLayout([
            ['id' => 'dashboardWidget-trainingGoals', 'widget' => 'trainingGoals', 'width' => 33, 'config' => ['goals' => []]],
        ]);

        $this->expectExceptionObject(new CouldNotProcessCommand('The unit "minute" is not valid for goal type "distance"'));

        $this->commandBus->dispatch(ConfigureWidget::fromPayload([
            'dashboardWidgetId' => 'dashboardWidget-trainingGoals',
            'config' => ['goals' => [
                'weekly' => [
                    ['label' => 'Ride', 'type' => 'distance', 'unit' => 'minute', 'goal' => '100', 'sportTypesToInclude' => ['Ride']],
                ],
            ]],
        ]));
    }

    public function testItRejectsInvalidConfigurationWithACleanMessage(): void
    {
        $this->seedLayout([
            ['id' => 'dashboardWidget-mostRecentActivities', 'widget' => 'mostRecentActivities', 'width' => 66, 'config' => ['numberOfActivitiesToDisplay' => 5]],
        ]);

        $this->expectExceptionObject(CouldNotProcessCommand::withReason(
            'Configuration item "numberOfActivitiesToDisplay" must be set to a value of 1 or greater.'
        ));

        $this->commandBus->dispatch(ConfigureWidget::fromPayload([
            'dashboardWidgetId' => 'dashboardWidget-mostRecentActivities',
            'config' => ['numberOfActivitiesToDisplay' => '0'],
        ]));
    }

    public function testItThrowsWhenWidgetDoesNotExist(): void
    {
        $this->seedLayout([
            ['id' => 'dashboardWidget-streaks', 'widget' => 'streaks', 'width' => 33, 'config' => ['subtitle' => null, 'sportTypesToInclude' => []]],
        ]);

        $this->expectExceptionObject(new \RuntimeException('Dashboard widget "dashboardWidget-doesNotExist" does not exist.'));

        $this->commandBus->dispatch(ConfigureWidget::fromPayload([
            'dashboardWidgetId' => 'dashboardWidget-doesNotExist',
            'config' => ['subtitle' => 'Keep it up'],
        ]));
    }

    private function seedLayout(array $layout): void
    {
        $this->keyValueStore->save(KeyValue::fromState(
            Key::DASHBOARD,
            Value::fromString(Json::encode($layout)),
        ));
    }

    private function storedConfigFor(string $id): array
    {
        $layout = Json::decode((string) $this->keyValueStore->find(Key::DASHBOARD));
        foreach ($layout as $item) {
            if ($item['id'] === $id) {
                return $item['config'];
            }
        }

        $this->fail(sprintf('Widget "%s" not found in stored layout.', $id));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->keyValueStore = $this->getContainer()->get(KeyValueStore::class);
    }
}
