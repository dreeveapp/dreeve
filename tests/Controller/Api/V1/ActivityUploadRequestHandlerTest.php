<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use App\Domain\Api\Token;
use App\Domain\Import\ImportMode;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class ActivityUploadRequestHandlerTest extends ControllerWebTestCase
{
    private const string PATH = '/api/v1/activity/upload';

    private Token $token;
    private FilesystemOperator $filesystem;

    #[DataProvider('provideMethods')]
    public function testUpload(string $method): void
    {
        $this->client->request($method, self::PATH, files: ['file' => $this->fixture('activity.fit')], server: $this->authorized());

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $this->assertSame('queued', Json::decode((string) $this->client->getResponse()->getContent())['status']);

        $this->assertTrue($this->filesystem->fileExists('watch/activity.fit'));
        $this->assertSame(
            file_get_contents(__DIR__.'/fixtures/activity.fit'),
            $this->filesystem->read('watch/activity.fit'),
        );
        // The staging folder exists only for the atomic hand off to the importer.
        $this->assertFalse($this->filesystem->fileExists('watch/.uploads/activity.fit'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideMethods(): iterable
    {
        // Gadgetbridge's HTTP method is a client side parameter, so both have to work.
        yield 'POST' => ['POST'];
        yield 'PUT' => ['PUT'];
    }

    public function testItDoesNotOverwriteAnExistingFile(): void
    {
        $this->client->request('POST', self::PATH, files: ['file' => $this->fixture('activity.fit')], server: $this->authorized());
        $this->client->request('POST', self::PATH, files: ['file' => $this->fixture('activity.fit')], server: $this->authorized());

        // Losing a ride is unrecoverable, a duplicate is not.
        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->assertSame(['watch/activity.fit', 'watch/activity-1.fit'], $this->watchDirectoryContents());
    }

    public function testItRejectsAnUnauthenticatedUpload(): void
    {
        $this->client->request('POST', self::PATH, files: ['file' => $this->fixture('activity.fit')]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $this->assertFalse($this->filesystem->fileExists('watch/activity.fit'));
    }

    public function testItRejectsAMissingFilePart(): void
    {
        $this->client->request('POST', self::PATH, server: $this->authorized(['CONTENT_TYPE' => 'multipart/form-data; boundary=x']));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame('missing_file', $this->errorCode());
    }

    public function testItRejectsANonMultipartBody(): void
    {
        $this->client->request('POST', self::PATH, server: $this->authorized(['CONTENT_TYPE' => 'application/json']), content: '{}');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        $this->assertSame('unsupported_media_type', $this->errorCode());
    }

    public function testItRejectsABodyThatExceededPostMaxSize(): void
    {
        // Over post_max_size PHP discards $_FILES entirely with no error flag, leaving only a
        // non-zero Content-Length to tell it apart from a genuinely empty request.
        $this->client->request('POST', self::PATH, server: $this->authorized([
            'CONTENT_TYPE' => 'multipart/form-data; boundary=x',
            'CONTENT_LENGTH' => '999999999',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        $this->assertSame('file_too_large', $this->errorCode());
    }

    public function testAMultipartBodyWithoutTheFilePartIsNotTreatedAsTooLarge(): void
    {
        // A body carrying other fields has a non-zero Content-Length too, so only comparing it
        // against post_max_size tells the two apart.
        $this->client->request('POST', self::PATH, parameters: ['other' => '1'], server: $this->authorized([
            'CONTENT_TYPE' => 'multipart/form-data; boundary=x',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame('missing_file', $this->errorCode());
    }

    #[DataProvider('provideUploadErrors')]
    public function testItMapsPhpUploadErrors(int $uploadError, int $expectedStatusCode, string $expectedErrorCode): void
    {
        $file = new UploadedFile(__DIR__.'/fixtures/activity.fit', 'activity.fit', null, $uploadError, true);

        $this->client->request('POST', self::PATH, files: ['file' => $file], server: $this->authorized());

        $this->assertResponseStatusCodeSame($expectedStatusCode);
        $this->assertSame($expectedErrorCode, $this->errorCode());
        $this->assertFalse($this->filesystem->fileExists('watch/activity.fit'));
    }

    /**
     * @return iterable<string, array{int, int, string}>
     */
    public static function provideUploadErrors(): iterable
    {
        yield 'over upload_max_filesize' => [UPLOAD_ERR_INI_SIZE, Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'file_too_large'];
        yield 'over the form limit' => [UPLOAD_ERR_FORM_SIZE, Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'file_too_large'];
        yield 'partial upload' => [UPLOAD_ERR_PARTIAL, Response::HTTP_BAD_REQUEST, 'missing_file'];
        yield 'no file' => [UPLOAD_ERR_NO_FILE, Response::HTTP_BAD_REQUEST, 'missing_file'];
        yield 'no tmp dir' => [UPLOAD_ERR_NO_TMP_DIR, Response::HTTP_INTERNAL_SERVER_ERROR, 'internal_error'];
        yield 'cannot write' => [UPLOAD_ERR_CANT_WRITE, Response::HTTP_INTERNAL_SERVER_ERROR, 'internal_error'];
    }

    #[DataProvider('provideRejectedFiles')]
    public function testItRejectsFilesItCannotImport(string $fixture, string $filename): void
    {
        $this->client->request('POST', self::PATH, files: ['file' => $this->fixture($fixture, $filename)], server: $this->authorized());

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertSame('unsupported_file_type', $this->errorCode());
        // Nothing hostile ever reaches the filesystem.
        $this->assertSame([], $this->watchDirectoryContents());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRejectedFiles(): iterable
    {
        yield 'unsupported extension' => ['activity.fit', 'notes.txt'];
        yield 'no extension' => ['activity.fit', 'activity'];
        yield 'dotfile with no stem' => ['activity.fit', '.fit'];
        yield 'null byte' => ['activity.fit', "evil\0.fit"];
        yield 'over the length limit' => ['activity.fit', str_repeat('a', 250).'.fit'];
    }

    #[DataProvider('provideTraversalAttempts')]
    public function testItSanitisesPathTraversalAttempts(string $uploadedAs): void
    {
        $this->client->request('POST', self::PATH, files: ['file' => $this->fixture('activity.fit', $uploadedAs)], server: $this->authorized());

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        // basename() plus the backslash swap keep it inside watch/, so nothing escapes to the
        // project root the way it could before the watch directory was hardened.
        $this->assertSame(['watch/evil.fit'], $this->watchDirectoryContents());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTraversalAttempts(): iterable
    {
        yield 'windows traversal' => ['..\\evil.fit'];
        yield 'unix traversal' => ['../../evil.fit'];
        yield 'absolute path' => ['/etc/evil.fit'];
    }

    public function testItAcceptsAGpxFile(): void
    {
        $this->client->request('POST', self::PATH, files: ['file' => $this->fixture('activity.gpx')], server: $this->authorized());

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        $this->assertTrue($this->filesystem->fileExists('watch/activity.gpx'));
    }

    public function testItRejectsUploadsInStravaApiMode(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);

        $this->client->request('POST', self::PATH, files: ['file' => $this->fixture('activity.fit')], server: $this->authorized());

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->assertSame('import_mode_not_files', $this->errorCode());
        $this->assertFalse($this->filesystem->fileExists('watch/activity.fit'));
    }

    private function fixture(string $fixture, ?string $uploadedAs = null): UploadedFile
    {
        return new UploadedFile(__DIR__.'/fixtures/'.$fixture, $uploadedAs ?? $fixture, null, null, true);
    }

    /**
     * @param array<string, string> $extra
     *
     * @return array<string, string>
     */
    private function authorized(array $extra = []): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token, ...$extra];
    }

    private function errorCode(): string
    {
        return Json::decode((string) $this->client->getResponse()->getContent())['error'];
    }

    /**
     * @return list<string>
     */
    private function watchDirectoryContents(): array
    {
        return $this->filesystem->listContents('watch', true)
            ->map(static fn ($attributes): string => $attributes->path())
            ->toArray();
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

        $this->filesystem = $this->getContainer()->get(FilesystemOperator::class);
    }
}
