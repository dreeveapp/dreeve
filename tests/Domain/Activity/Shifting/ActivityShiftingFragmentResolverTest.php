<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Shifting;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Shifting\ActivityDrivetrainUsageRepository;
use App\Domain\Activity\Shifting\DrivetrainPosition;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class ActivityShiftingFragmentResolverTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private const string ACTIVITY_ID = 'activity-9542782314';

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();
        $this->addDrivetrainUsages();

        $this->client->request('GET', '/api/internal/fragment/partial/activities/'.self::ACTIVITY_ID.'/shifting');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWithoutDrivetrainUsages(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/internal/fragment/partial/activities/'.self::ACTIVITY_ID.'/shifting');

        $this->assertResponseIsSuccessful();
        $this->assertEmpty($this->client->getResponse()->getContent());
    }

    public function testItEntersTheRenderCache(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();
        $this->addDrivetrainUsages();

        $this->client->request('GET', '/api/internal/fragment/partial/activities/'.self::ACTIVITY_ID.'/shifting');

        $this->assertResponseHeaderSame('X-Dreeve-Cache', 'MISS');
        $this->assertResponseHeaderSame('X-Dreeve-Cache-Tags', 'settings.appearance, settings.general, activities.9542782314, activities');
    }

    public function testItIsNotServedAsAPageFragment(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/internal/fragment/page/activities/'.self::ACTIVITY_ID.'/shifting');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItDoesNotResolveAnActivityThatDoesNotExist(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/api/internal/fragment/partial/activities/activity-1/shifting');

        $this->assertResponseStatusCodeSame(404);
    }

    private function addDrivetrainUsages(): void
    {
        $activityDrivetrainUsageRepository = $this->getContainer()->get(ActivityDrivetrainUsageRepository::class);

        $gears = [
            [DrivetrainPosition::FRONT, 2, 53, 13615, 0],
            [DrivetrainPosition::REAR, 1, 26, 20, 1],
            [DrivetrainPosition::REAR, 4, 19, 351, 43],
            [DrivetrainPosition::REAR, 5, 17, 1332, 117],
            [DrivetrainPosition::REAR, 6, 16, 7219, 140],
            [DrivetrainPosition::REAR, 7, 15, 4554, 63],
        ];

        foreach ($gears as [$position, $gearNumber, $teeth, $timeInSeconds, $shiftCount]) {
            $activityDrivetrainUsageRepository->add(
                ActivityDrivetrainUsageBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromString(self::ACTIVITY_ID))
                    ->withGear($position, $gearNumber, $teeth)
                    ->withTimeInSeconds($timeInSeconds)
                    ->withShiftCount($shiftCount)
                    ->build()
            );
        }
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }
}
