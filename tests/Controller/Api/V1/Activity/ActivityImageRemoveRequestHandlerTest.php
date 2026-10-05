<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Import\ImportMode;
use App\Infrastructure\Security\Api\Token;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

class ActivityImageRemoveRequestHandlerTest extends ControllerWebTestCase
{
    private const string KEPT_IMAGE = 'files/activities/9b1d1f3e-3c7a-4a52-8f6e-2f0b8c6d4a11.jpg';
    private const string REMOVED_IMAGE = 'files/activities/0025176c-5652-11ee-923d-02424dd627d5.png';
    private const string PATH = '/api/v1/activities/activity-1/images/activityImage-0025176c-5652-11ee-923d-02424dd627d5';

    private Token $token;
    private ActivityRepository $activityRepository;
    private FilesystemOperator $fileStorage;

    public function testItRemovesAnImage(): void
    {
        $this->client->request('DELETE', self::PATH, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        $this->assertSame(['/'.self::KEPT_IMAGE], $this->activityRepository->find(ActivityId::fromUnprefixed('1'))->getLocalImagePaths());
        $this->assertFalse($this->fileStorage->fileExists('activities/0025176c-5652-11ee-923d-02424dd627d5.png'));
        $this->assertTrue($this->fileStorage->fileExists('activities/9b1d1f3e-3c7a-4a52-8f6e-2f0b8c6d4a11.jpg'));
    }

    #[DataProvider('provideUnknownImages')]
    public function testItReportsAnUnknownImageAsNotFound(string $imageId): void
    {
        $this->client->request('DELETE', '/api/v1/activities/activity-1/images/'.$imageId, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame('not_found', $this->errorCode());
        $this->assertSame(['/'.self::KEPT_IMAGE, '/'.self::REMOVED_IMAGE], $this->activityRepository->find(ActivityId::fromUnprefixed('1'))->getLocalImagePaths());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownImages(): iterable
    {
        yield 'unknown image' => ['activityImage-6f1c2a9e-0000-4000-8000-000000000000'];
        yield 'malformed image id' => ['0025176c-5652-11ee-923d-02424dd627d5'];
    }

    public function testItReportsAnImageOfAnotherActivityAsNotFound(): void
    {
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->build(),
            rawData: [],
        ));

        $this->client->request(
            'DELETE',
            '/api/v1/activities/activity-2/images/activityImage-0025176c-5652-11ee-923d-02424dd627d5',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertTrue($this->fileStorage->fileExists('activities/0025176c-5652-11ee-923d-02424dd627d5.png'));
    }

    #[DataProvider('provideUnknownActivities')]
    public function testItReportsAnUnknownActivityAsNotFound(string $activityId): void
    {
        $this->client->request(
            'DELETE',
            '/api/v1/activities/'.$activityId.'/images/activityImage-0025176c-5652-11ee-923d-02424dd627d5',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame('not_found', $this->errorCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownActivities(): iterable
    {
        yield 'unknown activity' => ['activity-999'];
        yield 'malformed activity id' => ['not-an-activity-id'];
    }

    public function testItRejectsRemovingAnImageInStravaApiMode(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);

        $this->client->request('DELETE', self::PATH, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertSame('import_mode_not_files', $this->errorCode());
        $this->assertTrue($this->fileStorage->fileExists('activities/0025176c-5652-11ee-923d-02424dd627d5.png'));
    }

    public function testItRejectsAnUnauthenticatedRequest(): void
    {
        $this->client->request('DELETE', self::PATH);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertTrue($this->fileStorage->fileExists('activities/0025176c-5652-11ee-923d-02424dd627d5.png'));
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

        $this->activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $this->fileStorage = $this->getContainer()->get('file.storage');

        $this->fileStorage->write('activities/9b1d1f3e-3c7a-4a52-8f6e-2f0b8c6d4a11.jpg', 'kept');
        $this->fileStorage->write('activities/0025176c-5652-11ee-923d-02424dd627d5.png', 'removed');
        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withLocalImagePaths(self::KEPT_IMAGE, self::REMOVED_IMAGE)
                ->build(),
            rawData: [],
        ));
    }
}
