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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class ActivityImageAddRequestHandlerTest extends ControllerWebTestCase
{
    private const string PATH = '/api/v1/activities/activity-1/images';
    private const string EXISTING_IMAGE = 'files/activities/9b1d1f3e-3c7a-4a52-8f6e-2f0b8c6d4a11.jpg';

    private Token $token;
    private ActivityRepository $activityRepository;
    private FilesystemOperator $fileStorage;

    #[DataProvider('provideImages')]
    public function testItAddsAnImage(string $fixture, string $uploadedAs, string $expectedExtension): void
    {
        $this->client->request(
            'POST',
            self::PATH,
            files: ['file' => new UploadedFile(__DIR__.'/fixtures/'.$fixture, $uploadedAs, null, null, true)],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame(
            [
                'id' => 'activityImage-0025176c-5652-11ee-923d-02424dd627d5',
                'url' => 'http://localhost:8080/files/activities/0025176c-5652-11ee-923d-02424dd627d5.'.$expectedExtension,
            ],
            Json::decode((string) $this->client->getResponse()->getContent()),
        );
        $this->assertSame(
            ['/'.self::EXISTING_IMAGE, '/files/activities/0025176c-5652-11ee-923d-02424dd627d5.'.$expectedExtension],
            $this->activityRepository->find(ActivityId::fromUnprefixed('1'))->getLocalImagePaths(),
        );
        $this->assertSame(
            file_get_contents(__DIR__.'/fixtures/'.$fixture),
            $this->fileStorage->read('activities/0025176c-5652-11ee-923d-02424dd627d5.'.$expectedExtension),
        );
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideImages(): iterable
    {
        yield 'jpg' => ['image.jpg', 'image.jpg', 'jpg'];
        yield 'png' => ['image.png', 'image.png', 'png'];
        yield 'webp' => ['image.webp', 'image.webp', 'webp'];
        yield 'no extension' => ['image.png', 'header', 'png'];
        yield 'extension that does not match the content' => ['image.png', 'header.jpg', 'png'];
    }

    public function testTheAddedImageIsPartOfTheActivity(): void
    {
        $this->client->request(
            'POST',
            self::PATH,
            files: ['file' => new UploadedFile(__DIR__.'/fixtures/image.jpg', 'image.jpg', null, null, true)],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token],
        );
        $this->client->request('GET', '/api/v1/activities/activity-1', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertSame(
            [
                'activityImage-9b1d1f3e-3c7a-4a52-8f6e-2f0b8c6d4a11',
                'activityImage-0025176c-5652-11ee-923d-02424dd627d5',
            ],
            array_column(Json::decode((string) $this->client->getResponse()->getContent())['images'], 'id'),
        );
    }

    public function testItRejectsAFileThatIsNotAnImage(): void
    {
        $this->client->request(
            'POST',
            self::PATH,
            files: ['file' => new UploadedFile(__DIR__.'/fixtures/not-an-image.jpg', 'not-an-image.jpg', null, null, true)],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame('unsupported_file_type', $this->errorCode());
        $this->assertSame(['/'.self::EXISTING_IMAGE], $this->activityRepository->find(ActivityId::fromUnprefixed('1'))->getLocalImagePaths());
        $this->assertFalse($this->fileStorage->fileExists('activities/0025176c-5652-11ee-923d-02424dd627d5.jpg'));
    }

    public function testItRejectsAMissingFilePart(): void
    {
        $this->client->request(
            'POST',
            self::PATH,
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, 'CONTENT_TYPE' => 'multipart/form-data; boundary=x'],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame('missing_file', $this->errorCode());
    }

    #[DataProvider('provideUnknownActivities')]
    public function testItReportsAnUnknownActivityAsNotFound(string $activityId): void
    {
        $this->client->request(
            'POST',
            '/api/v1/activities/'.$activityId.'/images',
            files: ['file' => new UploadedFile(__DIR__.'/fixtures/image.jpg', 'image.jpg', null, null, true)],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame('not_found', $this->errorCode());
        $this->assertFalse($this->fileStorage->fileExists('activities/0025176c-5652-11ee-923d-02424dd627d5.jpg'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownActivities(): iterable
    {
        yield 'unknown activity' => ['activity-999'];
        yield 'malformed activity id' => ['not-an-activity-id'];
    }

    public function testItRejectsAddingAnImageInStravaApiMode(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);

        $this->client->request(
            'POST',
            self::PATH,
            files: ['file' => new UploadedFile(__DIR__.'/fixtures/image.jpg', 'image.jpg', null, null, true)],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertSame('import_mode_not_files', $this->errorCode());
        $this->assertSame(['/'.self::EXISTING_IMAGE], $this->activityRepository->find(ActivityId::fromUnprefixed('1'))->getLocalImagePaths());
    }

    public function testItRejectsAnUnauthenticatedRequest(): void
    {
        $this->client->request(
            'POST',
            self::PATH,
            files: ['file' => new UploadedFile(__DIR__.'/fixtures/image.jpg', 'image.jpg', null, null, true)],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertSame(['/'.self::EXISTING_IMAGE], $this->activityRepository->find(ActivityId::fromUnprefixed('1'))->getLocalImagePaths());
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

        $this->activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withLocalImagePaths(self::EXISTING_IMAGE)
                ->build(),
            rawData: [],
        ));
    }
}
