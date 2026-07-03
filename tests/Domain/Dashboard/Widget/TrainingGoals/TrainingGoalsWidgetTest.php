<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingGoals;

use App\Domain\Dashboard\Widget\TrainingGoals\TrainingGoalsWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class TrainingGoalsWidgetTest extends ContainerTestCase
{
    use ProvideTestData;
    use MatchesSnapshots;

    private TrainingGoalsWidget $widget;

    public function testGetDefaultConfiguration(): void
    {
        $this->assertEquals(
            WidgetConfiguration::empty()
                ->add('goals', []),
            $this->widget->getDefaultConfiguration()
        );
    }

    public function testRender(): void
    {
        $this->provideFullTestSet();

        $config = WidgetConfiguration::empty()
            ->add('goals', [
                'weekly' => [
                    ['label' => 'Cycling',  'type' => 'distance', 'unit' => 'km', 'goal' => 200,  'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide']],
                    ['label' => 'Cycling',  'type' => 'elevation', 'unit' => 'm', 'goal' => 1000,  'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide']],
                    ['label' => 'Cycling',  'type' => 'movingTime', 'unit' => 'hour', 'goal' => 2,  'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide']],
                    ['label' => 'Cycling',  'type' => 'calories', 'unit' => 'hour', 'goal' => 250,  'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide']],
                    ['label' => 'Cycling',  'type' => 'numberOfActivities', 'unit' => 'hour', 'goal' => 8,  'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide']],
                    ['label' => 'Running',  'type' => 'movingTime', 'unit' => 'hour', 'goal' => 2,  'sportTypesToInclude' => ['Run']],
                ],
                'monthly' => [
                    ['label' => 'Cycling',  'type' => 'distance', 'unit' => 'km', 'goal' => 200,  'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide']],
                    ['label' => 'Cycling',  'type' => 'elevation', 'unit' => 'm', 'goal' => 1000,  'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide']],
                    ['label' => 'Cycling',  'type' => 'movingTime', 'unit' => 'hour', 'goal' => 2,  'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide']],
                    ['label' => 'Running',  'type' => 'movingTime', 'unit' => 'hour', 'goal' => 2,  'sportTypesToInclude' => ['Run']],
                ],
                'yearly' => [
                    ['label' => 'Cycling',  'type' => 'distance', 'unit' => 'km', 'goal' => 200,  'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide']],
                    ['label' => 'Cycling',  'type' => 'elevation', 'unit' => 'm', 'goal' => 1000,  'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide']],
                    ['label' => 'Cycling',  'type' => 'movingTime', 'unit' => 'hour', 'goal' => 2,  'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide']],
                    ['label' => 'Running',  'type' => 'movingTime', 'unit' => 'hour', 'goal' => 2,  'sportTypesToInclude' => ['Run']],
                ],
                'lifetime' => [
                    ['label' => 'Cycling',  'type' => 'distance', 'unit' => 'km', 'goal' => 200,  'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide']],
                    ['label' => 'Cycling',  'type' => 'elevation', 'unit' => 'm', 'goal' => 1000,  'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide']],
                    ['label' => 'Cycling',  'type' => 'movingTime', 'unit' => 'hour', 'goal' => 2,  'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide']],
                    ['label' => 'Running',  'type' => 'movingTime', 'unit' => 'hour', 'goal' => 2,  'sportTypesToInclude' => ['Run']],
                ],
            ]);

        $render = $this->widget->render(
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: $config
        );
        $this->assertMatchesHtmlSnapshot($render);
    }

    public function testRenderWhenNoGoals(): void
    {
        $render = $this->widget->render(
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()
        );
        $this->assertNull($render);
    }

    public function testRenderWithRestrictToDateRange(): void
    {
        $this->provideFullTestSet();

        $config = WidgetConfiguration::empty()
            ->add('goals', [
                'weekly' => [
                    ['label' => 'Running (base)',  'type' => 'distance', 'unit' => 'km', 'goal' => 25,  'sportTypesToInclude' => ['Run'], 'restrictToDateRange' => ['from' => '2025-01-01', 'to' => '2025-03-31']],
                    ['label' => 'Running (build)', 'type' => 'distance', 'unit' => 'km', 'goal' => 40,  'sportTypesToInclude' => ['Run'], 'restrictToDateRange' => ['from' => '2025-10-01', 'to' => '2025-12-31']],
                    ['label' => 'Cycling',         'type' => 'distance', 'unit' => 'km', 'goal' => 200, 'sportTypesToInclude' => ['Ride', 'MountainBikeRide', 'GravelRide', 'VirtualRide']],
                ],
            ]);

        $render = $this->widget->render(
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: $config
        );
        $this->assertMatchesHtmlSnapshot($render);
    }

    public function testRenderReturnsNullWhenAllGoalsFilteredByDateRange(): void
    {
        $config = WidgetConfiguration::empty()
            ->add('goals', [
                'weekly' => [
                    ['label' => 'Running', 'type' => 'distance', 'unit' => 'km', 'goal' => 25, 'sportTypesToInclude' => ['Run'], 'restrictToDateRange' => ['from' => '2025-01-01', 'to' => '2025-03-31']],
                ],
            ]);

        $render = $this->widget->render(
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: $config
        );
        $this->assertNull($render);
    }

    public function testRenderWhenNoGoalsUseCaseTwo(): void
    {
        $render = $this->widget->render(
            now: SerializableDateTime::fromString('2025-10-16'),
            configuration: WidgetConfiguration::empty()
            ->add('goals', ['weekly' => []])
        );
        $this->assertNull($render);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->widget = $this->getContainer()->get(TrainingGoalsWidget::class);
    }
}
