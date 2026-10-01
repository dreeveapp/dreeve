<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\ImportSource;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Import\ImportMode;
use App\Infrastructure\Security\Api\Token;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\Response;

class ActivityUpdateRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;

    private const string PATH = '/api/v1/activities/activity-1';

    private Token $token;
    private ActivityRepository $activityRepository;

    public function testItUpdatesAnActivity(): void
    {
        $this->client->request(
            'PATCH',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: (string) file_get_contents(__DIR__.'/fixtures/patch-activity.json'),
        );

        $this->assertResponseIsSuccessful();
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));
        $this->assertSame('Corrected ride', $activity->getOriginalName());
        $this->assertSame(SportType::GRAVEL_RIDE, $activity->getSportType());
        $this->assertSame('Rerouted around the closed bridge', $activity->getDescription());
    }

    public function testItOnlyUpdatesTheProvidedFields(): void
    {
        $this->client->request(
            'PATCH',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: (string) file_get_contents(__DIR__.'/fixtures/patch-activity-name-only.json'),
        );

        $this->assertResponseIsSuccessful();

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));
        $this->assertSame('Only the name changed', $activity->getOriginalName());
        $this->assertSame(SportType::RIDE, $activity->getSportType());
        $this->assertSame('Original description', $activity->getDescription());
        $this->assertEquals(GearId::fromUnprefixed('1'), $activity->getGearId());
        $this->assertSame(512, $activity->getCalories());
        $this->assertSame('Garmin Edge 540', $activity->getDeviceName());
        $this->assertTrue($activity->isCommute());
        $this->assertTrue($activity->isGroupActivity());
        $this->assertSame(['/files/activities/photo.jpg'], $activity->getLocalImagePaths());
    }

    public function testItClearsTheDescription(): void
    {
        $this->client->request(
            'PATCH',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: (string) file_get_contents(__DIR__.'/fixtures/patch-activity-clear-description.json'),
        );

        $this->assertResponseIsSuccessful();

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));
        $this->assertSame('', $activity->getDescription());
        $this->assertSame('Original activity', $activity->getOriginalName());
    }

    public function testItUpdatesTheGear(): void
    {
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('2'))
                ->build()
        );

        $this->client->request(
            'PATCH',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: (string) file_get_contents(__DIR__.'/fixtures/patch-activity-gear.json'),
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame('gear-2', Json::decode((string) $this->client->getResponse()->getContent())['gearId']);

        $activity = $this->activityRepository->find(ActivityId::fromUnprefixed('1'));
        $this->assertEquals(GearId::fromUnprefixed('2'), $activity->getGearId());
        $this->assertSame('Original activity', $activity->getOriginalName());
    }

    public function testItClearsTheGear(): void
    {
        $this->client->request(
            'PATCH',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: (string) file_get_contents(__DIR__.'/fixtures/patch-activity-clear-gear.json'),
        );

        $this->assertResponseIsSuccessful();
        $this->assertNull(Json::decode((string) $this->client->getResponse()->getContent())['gearId']);
        $this->assertNull($this->activityRepository->find(ActivityId::fromUnprefixed('1'))->getGearId());
    }

    #[DataProvider('provideInvalidRequestBodies')]
    public function testItRejectsInvalidRequestBodies(string $content): void
    {
        $this->client->request(
            'PATCH',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: $content,
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame('bad_request', Json::decode((string) $this->client->getResponse()->getContent())['error']);
        $this->assertSame('Original activity', $this->activityRepository->find(ActivityId::fromUnprefixed('1'))->getOriginalName());
    }

    public static function provideInvalidRequestBodies(): iterable
    {
        yield 'invalid JSON' => ['{'];
        yield 'JSON scalar' => ['"name"'];
        yield 'JSON list' => ['["name"]'];
        yield 'empty object' => ['{}'];
        yield 'unknown field' => ['{"name": "Ride", "calories": 200}'];
        yield 'unknown gear' => ['{"name": "Ride", "gearId": "gear-999"}'];
        yield 'unprefixed gear' => ['{"name": "Ride", "gearId": "1"}'];
        yield 'non-string gear' => ['{"name": "Ride", "gearId": 1}'];
        yield 'empty name' => ['{"name": "  "}'];
        yield 'non-string name' => ['{"name": 12}'];
        yield 'unknown sport type' => ['{"sportType": "Unicycling"}'];
    }

    public function testItReportsAnUnknownActivityAsNotFound(): void
    {
        $this->client->request(
            'PATCH',
            '/api/v1/activities/activity-2',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: (string) file_get_contents(__DIR__.'/fixtures/patch-activity.json'),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame('not_found', Json::decode((string) $this->client->getResponse()->getContent())['error']);
    }

    public function testItReportsAMalformedActivityIdAsNotFound(): void
    {
        $this->client->request(
            'PATCH',
            '/api/v1/activities/not-an-activity-id',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: (string) file_get_contents(__DIR__.'/fixtures/patch-activity.json'),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame('not_found', Json::decode((string) $this->client->getResponse()->getContent())['error']);
    }

    public function testItRejectsUpdatesInStravaApiMode(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);

        $this->client->request(
            'PATCH',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'application/json'],
            content: (string) file_get_contents(__DIR__.'/fixtures/patch-activity.json'),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertSame('import_mode_not_files', Json::decode((string) $this->client->getResponse()->getContent())['error']);
        $this->assertSame('Original activity', $this->activityRepository->find(ActivityId::fromUnprefixed('1'))->getOriginalName());
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }

    #[\Override]
    protected function prepareEnvironment(): void
    {
        $this->token = Token::generate();
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
        $_SERVER['IMPORT_MODE'] = $_ENV['IMPORT_MODE'] = ImportMode::FILES->value;
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        assert($activityRepository instanceof ActivityRepository);
        $this->activityRepository = $activityRepository;

        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withName('Original activity')
                ->withDescription('Original description')
                ->withSportType(SportType::RIDE)
                ->withImportSource(ImportSource::FIT_FILE)
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withCalories(512)
                ->withDeviceName('Garmin Edge 540')
                ->withIsCommute(true)
                ->withIsGroupActivity(true)
                ->withLocalImagePaths('files/activities/photo.jpg')
                ->build(),
            rawData: [],
        ));
    }
}
