<?php

declare(strict_types=1);

namespace App\Tests\Domain\Strava;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Gear\GearId;
use App\Domain\Segment\SegmentId;
use App\Domain\Strava\RateLimit\StravaRateLimitHasBeenReached;
use App\Domain\Strava\RateLimit\StravaRateLimits;
use App\Domain\Strava\Strava;
use App\Domain\Strava\StravaClientId;
use App\Domain\Strava\StravaClientSecret;
use App\Domain\Strava\StravaRefreshToken;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Infrastructure\ValueObject\String\Url;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use App\Tests\Infrastructure\Time\Sleep\NullSleep;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Psr\Log\NullLogger;

class SpyStrava extends Strava
{
    private int $numberOfCallsExecuted = 0;
    private int $maxNumberOfCallsBeforeTriggering429 = 0;
    private array $activities;
    private bool $triggerExceptionOnNextCall = false;
    private bool $triggerExceptionOnNextActivityCall = false;
    private bool $returnActivityWithoutSegmentEfforts = false;

    public function __construct(
        private readonly KernelProjectDir $kernelProjectDir,
    ) {
        parent::__construct(
            client: new Client(),
            stravaClientId: StravaClientId::fromString('clientId'),
            stravaClientSecret: StravaClientSecret::fromString('clientSecret'),
            stravaRefreshToken: StravaRefreshToken::fromString('refreshToken'),
            filesystemOperator: new Filesystem(new InMemoryFilesystemAdapter()),
            sleep: new NullSleep(),
            logger: new NullLogger(),
            clock: PausedClock::fromString('2025-11-02 23:45:12')
        );
        $this->activities = [
            'activity-1' => Json::decode((string) file_get_contents(__DIR__.'/fixtures/activities/activity-1.json')),
            'activity-2' => Json::decode((string) file_get_contents(__DIR__.'/fixtures/activities/activity-2.json')),
            'activity-3' => Json::decode((string) file_get_contents(__DIR__.'/fixtures/activities/activity-3.json')),
            'activity-4' => Json::decode((string) file_get_contents(__DIR__.'/fixtures/activities/activity-4.json')),
            'activity-5' => Json::decode((string) file_get_contents(__DIR__.'/fixtures/activities/activity-5.json')),
            'activity-6' => Json::decode((string) file_get_contents(__DIR__.'/fixtures/activities/activity-6.json')),
            'activity-skip' => Json::decode((string) file_get_contents(__DIR__.'/fixtures/activities/activity-skip.json')),
        ];
    }

    public function setMaxNumberOfCallsBeforeTriggering429(int $max): void
    {
        $this->numberOfCallsExecuted = 0;
        $this->maxNumberOfCallsBeforeTriggering429 = $max;
    }

    public function triggerExceptionOnNextCall(): void
    {
        $this->triggerExceptionOnNextCall = true;
    }

    public function triggerExceptionOnNextActivityCall(): void
    {
        $this->triggerExceptionOnNextActivityCall = true;
    }

    private function throw429IfMaxNumberOfCallsIsExceeded(): void
    {
        if ($this->numberOfCallsExecuted >= $this->maxNumberOfCallsBeforeTriggering429) {
            throw StravaRateLimitHasBeenReached::dailyReadLimit();
        }
    }

    public function returnActivityWithoutSegmentEfforts(): void
    {
        $this->returnActivityWithoutSegmentEfforts = true;
    }

    public function emptyGearIdOnActivities(): void
    {
        foreach ($this->activities as &$activity) {
            $activity['gear_id'] = null;
        }
        unset($activity);
    }

    #[\Override]
    public function getRateLimit(): ?StravaRateLimits
    {
        return StravaRateLimits::fromResponse(new Response(
            status: 200,
            headers: [
                'x-ratelimit-limit' => '200,2000',
                'x-ratelimit-usage' => '20,10',
                'x-readratelimit-limit' => '100,1000',
                'x-readratelimit-usage' => '10,14',
            ]
        ));
    }

    #[\Override]
    public function verifyAccessToken(): void
    {
    }

    #[\Override]
    public function getAthlete(): array
    {
        ++$this->numberOfCallsExecuted;
        $this->throw429IfMaxNumberOfCallsIsExceeded();

        return [
            'id' => 100,
        ];
    }

    #[\Override]
    public function getActivities(): array
    {
        if ($this->triggerExceptionOnNextCall) {
            $this->triggerExceptionOnNextCall = false;
            throw new RequestException(message: 'The error', request: new Request('GET', 'uri'), response: new Response(500, [], Json::encode(['error' => 'The error'])));
        }

        ++$this->numberOfCallsExecuted;
        $this->throw429IfMaxNumberOfCallsIsExceeded();

        return $this->activities;
    }

    #[\Override]
    public function getActivity(ActivityId $activityId): array
    {
        ++$this->numberOfCallsExecuted;
        $this->throw429IfMaxNumberOfCallsIsExceeded();

        if ($this->triggerExceptionOnNextActivityCall) {
            $this->triggerExceptionOnNextActivityCall = false;
            throw new RequestException(message: 'The error', request: new Request('GET', 'uri'), response: new Response(500, [], Json::encode(['error' => 'The error'])));
        }

        if ($this->returnActivityWithoutSegmentEfforts) {
            $this->returnActivityWithoutSegmentEfforts = false;

            return Json::decode('{"resource_state":3,"athlete":{"id":62214940,"resource_state":1},"name":"Wrong type","distance":6106.4,"moving_time":1001,"elapsed_time":1157,"total_elevation_gain":9,"type":"Ride","sport_type":"Ride","workout_type":10,"id":1,"start_date":"2023-09-11T19:05:38Z","start_date_local":"2023-09-11T21:05:38Z","timezone":"(GMT+01:00) Europe/Brussels","utc_offset":7200,"location_city":null,"location_state":null,"location_country":null,"achievement_count":3,"kudos_count":0,"comment_count":0,"athlete_count":1,"photo_count":0,"trainer":false,"commute":false,"manual":false,"private":false,"visibility":"everyone","flagged":false,"gear_id":"b12659861","start_latlng":[51.2,3.18],"end_latlng":[51.19,3.24],"average_speed":6.1,"max_speed":14.82,"average_watts":93.4,"kilojoules":93.4,"device_watts":false,"has_heartrate":true,"average_heartrate":133.3,"max_heartrate":159,"heartrate_opt_out":false,"display_hide_heartrate_option":true,"elev_high":11.2,"elev_low":3.3,"upload_id":10537559770,"upload_id_str":"10537559770","external_id":"Robin_Ingelbrecht_2023-09-11_21-05-37.tcx","from_accepted_tag":false,"pr_count":2,"total_photo_count":0,"has_kudoed":false,"description":"","calories":163,"perceived_exertion":null,"prefer_perceived_exertion":false,"gear":{"id":"b12659861","primary":false,"name":"Retro Race Bike","nickname":"Retro Race Bike","resource_state":2,"retired":false,"distance":1242107,"converted_distance":1242.1},"photos":{"primary":null,"count":0},"hide_from_home":true,"device_name":"Polar Vantage M","embed_token":"98606439ab8600a59e9185041262a135ad49ff47","private_note":"","available_zones":[],"athlete_weight":69,"start_date_timestamp":1694459138,"_id":154,"localImagePaths":[]}');
        }

        if (!array_key_exists((string) $activityId, $this->activities)) {
            throw new RequestException(message: 'Record Not Found', request: new Request('GET', 'uri'), response: new Response(404, [], Json::encode(['message' => 'Record Not Found'])));
        }

        return $this->activities[(string) $activityId];
    }

    #[\Override]
    public function getAllActivityStreams(ActivityId $activityId): array
    {
        ++$this->numberOfCallsExecuted;
        $this->throw429IfMaxNumberOfCallsIsExceeded();

        if ($this->triggerExceptionOnNextCall) {
            $this->triggerExceptionOnNextCall = false;
            throw new RequestException(message: 'The error', request: new Request('GET', 'uri'), response: new Response(500, [], Json::encode(['error' => 'The error'])));
        }

        if (ActivityId::fromUnprefixed('5') == $activityId) {
            return [
                [
                    'type' => 'trolol',
                    'data' => Json::decode((string) file_get_contents(__DIR__.'/../../fixtures/strava/streams/watts.json')),
                ],
            ];
        }

        return [
            [
                'type' => StreamType::WATTS->value,
                'data' => Json::decode((string) file_get_contents(__DIR__.'/../../fixtures/strava/streams/watts.json')),
            ],
            [
                'type' => 'distance',
                'data' => Json::decode((string) file_get_contents(__DIR__.'/../../fixtures/strava/streams/watts.json')),
            ],
        ];
    }

    #[\Override]
    public function getActivityPhotos(ActivityId $activityId): array
    {
        if ($this->triggerExceptionOnNextCall) {
            $this->triggerExceptionOnNextCall = false;
            throw new RequestException(message: 'The error', request: new Request('GET', 'uri'), response: new Response(500, [], Json::encode(['error' => 'The error'])));
        }
        ++$this->numberOfCallsExecuted;
        $this->throw429IfMaxNumberOfCallsIsExceeded();

        return [
            [
                'urls' => [
                    5000 => 'https://www.strava.com/path-to-image.png',
                ],
            ],
            [
                'urls' => [
                    2000 => 'https://www.strava.com/path-to-image.png',
                ],
            ],
        ];
    }

    #[\Override]
    public function getGear(GearId $gearId): array
    {
        if ($this->triggerExceptionOnNextCall) {
            $this->triggerExceptionOnNextCall = false;
            throw new RequestException(message: 'The error', request: new Request('GET', 'uri'), response: new Response(500, [], Json::encode(['error' => 'The error'])));
        }
        ++$this->numberOfCallsExecuted;
        $this->throw429IfMaxNumberOfCallsIsExceeded();

        $gears = [
            'gear-b12659861' => Json::decode('{"id":"b12659861","primary":false,"name":"Retro Race Bike","nickname":"Retro Race Bike","resource_state":3,"retired":false,"distance":1450147,"converted_distance":1450.1,"brand_name":null,"model_name":null,"frame_type":3,"description":"","weight":15,"createdOn":1687251898,"_id":1}'),
            'gear-b12659743' => Json::decode('{"id":"b12659743","primary":false,"name":"Zwift Hub","nickname":"Zwift Hub","resource_state":3,"retired":false,"distance":2831825,"converted_distance":2964.2,"brand_name":null,"model_name":null,"frame_type":3,"description":"This is a virtual trainer","weight":15,"createdOn":1687251898,"_id":2}'),
            'gear-b12659792' => Json::decode('{"id":"b12659792","primary":false,"name":"Elite Direto XR-T","nickname":"Elite Direto XR-T","resource_state":3,"retired":true,"distance":946270,"converted_distance":946.3,"brand_name":null,"model_name":null,"frame_type":3,"description":"This is a virtual trainer","weight":15,"createdOn":1687251899,"_id":3}'),
        ];

        return $gears[(string) $gearId];
    }

    #[\Override]
    public function getSegment(SegmentId $segmentId): array
    {
        if ($this->triggerExceptionOnNextCall) {
            $this->triggerExceptionOnNextCall = false;
            throw new RequestException(message: 'The error', request: new Request('GET', 'uri'), response: new Response(500, [], Json::encode(['error' => 'The error'])));
        }

        ++$this->numberOfCallsExecuted;
        $this->throw429IfMaxNumberOfCallsIsExceeded();

        $segments = [
            'segment-1' => ['map' => ['polyline' => 'DaLine']],
            'segment-2' => ['map' => ['polyline' => 'DaLine2']],
        ];

        return $segments[(string) $segmentId];
    }

    #[\Override]
    public function getWebhookSubscription(): array
    {
        return [
            [
                'id' => 'le-id',
                'application_id' => 'le-application-id',
                'callback_url' => 'le-url',
                'created_at' => '2025-01-01',
                'updated_at' => '2025-01-01',
            ],
            [
                'id' => 'le-id-2',
                'application_id' => 'le-application-id-2',
                'callback_url' => 'le-url-2',
                'created_at' => '2025-01-01',
                'updated_at' => '2025-01-01',
            ],
        ];
    }

    #[\Override]
    public function createWebhookSubscription(Url $callbackUrl, string $verifyToken): array
    {
        return ['id' => 'le-id'];
    }

    #[\Override]
    public function deleteWebhookSubscription(string $subscriptionId): void
    {
    }

    #[\Override]
    public function getChallengesOnTrophyCase(): array
    {
        if ($this->triggerExceptionOnNextCall) {
            $this->triggerExceptionOnNextCall = false;
            throw new RequestException(message: 'The error', request: new Request('GET', 'uri'), response: new Response(500, [], Json::encode(['error' => 'The error'])));
        }

        return [
            [
                'name' => 'Challenge 1',
                'teaser' => 'Craziest challenge ever',
                'logo_url' => $this->kernelProjectDir.'/tests/cat.webp',
                'url' => 'https://www.strava.com',
                'challenge_id' => '123456',
                'completedOn' => SerializableDateTime::fromString('2023-10-01'),
            ],
            [
                'name' => 'Challenge 1',
                'teaser' => 'Craziest challenge ever',
                'logo_url' => $this->kernelProjectDir.'/tests/cat.webp',
                'url' => 'https://www.strava.com',
                'challenge_id' => '123456',
                'completedOn' => SerializableDateTime::fromString('2023-10-01'),
            ],
            [
                'name' => 'Challenge 2',
                'teaser' => 'Craziest challenge ever',
                'logo_url' => $this->kernelProjectDir.'/tests/cat.webp',
                'url' => 'https://www.strava.com',
                'challenge_id' => '654321',
                'completedOn' => SerializableDateTime::fromString('2023-10-01'),
            ],
        ];
    }

    #[\Override]
    public function downloadImage(string $uri): string
    {
        if ($this->triggerExceptionOnNextCall) {
            $this->triggerExceptionOnNextCall = false;
            throw new \RuntimeException('WAW ERROR');
        }
        ++$this->numberOfCallsExecuted;
        $this->throw429IfMaxNumberOfCallsIsExceeded();

        return '/tests/cat.webp';
    }
}
