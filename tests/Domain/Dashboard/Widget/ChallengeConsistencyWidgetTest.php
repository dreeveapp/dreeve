<?php

namespace App\Tests\Domain\Dashboard\Widget;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Dashboard\InvalidDashboardLayout;
use App\Domain\Dashboard\Widget\ChallengeConsistencyWidget;
use App\Domain\Dashboard\Widget\WidgetConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class ChallengeConsistencyWidgetTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private ChallengeConsistencyWidget $widget;

    public function testRender(): void
    {
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-03 00:00:00'))
                ->withSportType(SportType::RIDE)
                ->build(),
            []
        ));

        $this->assertMatchesHtmlSnapshot(
            $this->widget->render(
                now: SerializableDateTime::fromString('2025-12-31'),
                configuration: $this->widget->getDefaultConfiguration()
            )
        );
    }

    public function testGuardValidConfigurationWithValidConfigItShouldNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->widget->guardValidConfiguration($this->widget->getDefaultConfiguration());
    }

    public function testGuardValidConfigurationItShouldThrow(): void
    {
        $configuration = WidgetConfiguration::empty()->add('challenges', [
            ['label' => 'Ride', 'type' => 'distance', 'unit' => 'km', 'goal' => 200, 'sportTypesToInclude' => ['lol']],
        ]);

        $this->expectExceptionObject(new InvalidDashboardLayout('"lol" is not a valid sport type'));
        $this->widget->guardValidConfiguration($configuration);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->widget = $this->getContainer()->get(ChallengeConsistencyWidget::class);
    }
}
