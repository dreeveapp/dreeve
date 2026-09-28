<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Stream\ActivityStreamRepository;
use App\Domain\Activity\Stream\StreamType;
use App\Domain\Api\Token;
use App\Domain\Import\ImportMode;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Stream\ActivityStreamBuilder;
use Symfony\Component\HttpFoundation\Response;

class ActivityDeleteRequestHandlerTest extends ControllerWebTestCase
{
    private const string PATH = '/api/v1/activities/activity-1';

    private Token $token;
    private ActivityRepository $activityRepository;
    private ActivityStreamRepository $activityStreamRepository;

    public function testItDeletesAnActivity(): void
    {
        $this->delete(self::PATH);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertFalse($this->activityStreamRepository->hasOneForActivityAndStreamType(ActivityId::fromUnprefixed('1'), StreamType::TIME));

        $this->expectException(EntityNotFound::class);
        $this->activityRepository->find(ActivityId::fromUnprefixed('1'));
    }

    public function testItReportsAnUnknownActivityAsNotFound(): void
    {
        $this->delete('/api/v1/activities/activity-2');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame('not_found', $this->errorCode());
    }

    public function testItReportsAMalformedActivityIdAsNotFound(): void
    {
        $this->delete('/api/v1/activities/not-an-activity-id');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame('not_found', $this->errorCode());
    }

    public function testItRejectsDeletesInStravaApiMode(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);

        $this->delete(self::PATH);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertSame('import_mode_not_files', $this->errorCode());
        $this->assertSame('Test activity', $this->activityRepository->find(ActivityId::fromUnprefixed('1'))->getOriginalName());
    }

    private function delete(string $path): void
    {
        $this->client->request('DELETE', $path, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);
    }

    private function errorCode(): string
    {
        return Json::decode((string) $this->client->getResponse()->getContent())['error'];
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

        $activityStreamRepository = $this->getContainer()->get(ActivityStreamRepository::class);
        assert($activityStreamRepository instanceof ActivityStreamRepository);
        $this->activityStreamRepository = $activityStreamRepository;

        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->build(),
            rawData: [],
        ));
        $this->activityStreamRepository->add(ActivityStreamBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('1'))
            ->withStreamType(StreamType::TIME)
            ->build());
    }
}
