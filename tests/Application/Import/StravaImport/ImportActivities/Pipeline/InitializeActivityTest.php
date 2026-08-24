<?php

namespace App\Tests\Application\Import\StravaImport\ImportActivities\Pipeline;

use App\Application\Import\StravaImport\ImportActivities\Pipeline\ActivityImportContext;
use App\Application\Import\StravaImport\ImportActivities\Pipeline\InitializeActivity;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearRepository;
use App\Infrastructure\Serialization\Json;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class InitializeActivityTest extends ContainerTestCase
{
    private InitializeActivity $initializeActivity;

    #[DataProvider('provideAthleteCounts')]
    public function testProcessReAppliesTheAthleteCountOfAnActivityWeAlreadyKnow(
        bool $wasGroupActivity,
        int $athleteCount,
        bool $expected,
    ): void {
        $activityId = ActivityId::fromUnprefixed('1');
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withIsGroupActivity($wasGroupActivity)
                ->build(),
            [],
        ));

        $rawStravaData = $this->rawStravaData();
        $rawStravaData['athlete_count'] = $athleteCount;

        $context = $this->initializeActivity->process(ActivityImportContext::create(
            activityId: $activityId,
            rawStravaData: $rawStravaData,
            isNewActivity: false,
        ));

        $this->assertEquals($expected, $context->getActivity()->isGroupActivity());
    }

    public function testProcessKeepsTheGroupActivityFlagWhenStravaDoesNotReportAnAthleteCount(): void
    {
        $activityId = ActivityId::fromUnprefixed('1');
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId($activityId)
                ->withIsGroupActivity(true)
                ->build(),
            [],
        ));

        $rawStravaData = $this->rawStravaData();
        unset($rawStravaData['athlete_count']);

        $context = $this->initializeActivity->process(ActivityImportContext::create(
            activityId: $activityId,
            rawStravaData: $rawStravaData,
            isNewActivity: false,
        ));

        $this->assertTrue($context->getActivity()->isGroupActivity());
    }

    /**
     * @return \Generator<string, array{bool, int, bool}>
     */
    public static function provideAthleteCounts(): \Generator
    {
        yield 'other athletes joined after the first import' => [false, 5, true];
        yield 'the other athletes are gone' => [true, 1, false];
        yield 'still a group activity' => [true, 3, true];
        yield 'still a solo activity' => [false, 1, false];
    }

    /**
     * @return array<string, mixed>
     */
    private function rawStravaData(): array
    {
        return Json::decode(file_get_contents(__DIR__.'/fixtures/raw-strava-activity.json') ?: '');
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->initializeActivity = new InitializeActivity(
            $this->getContainer()->get(ActivityRepository::class),
            $this->getContainer()->get(GearRepository::class),
        );
    }
}
