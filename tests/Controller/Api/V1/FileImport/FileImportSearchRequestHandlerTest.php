<?php

namespace App\Tests\Controller\Api\V1\FileImport;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ImportSource;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Domain\Import\FileImportStatus;
use App\Infrastructure\Security\Api\Token;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Import\FileImportBuilder;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\Response;

class FileImportSearchRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;

    private const string PATH = '/api/v1/file-imports';

    private Token $token;

    public function testItListsQueuedFilesFirstAndThenTheNewestImports(): void
    {
        $this->seedFileImports();

        $this->client->request('GET', self::PATH, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseIsSuccessful();
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    #[DataProvider('provideFilterScenarios')]
    public function testItAppliesFiltersToImportsButNotToQueuedFiles(string $queryString, array $expectedFilenames): void
    {
        $this->seedFileImports();

        $this->client->request('GET', self::PATH.$queryString, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertSame(
            $expectedFilenames,
            array_column(Json::decode((string) $this->client->getResponse()->getContent())['fileImports'], 'filename')
        );
    }

    public static function provideFilterScenarios(): iterable
    {
        yield 'a filename' => ['?filters[filename]=ride.fit', ['queued.gpx', 'ride.fit', 'ride.fit']];
        yield 'a single status' => ['?filters[status]=failed', ['queued.gpx', 'broken.tcx']];
        yield 'several comma separated statuses' => ['?filters[status]=failed,skipped', ['queued.gpx', 'broken.tcx', 'ride.fit']];
        yield 'a single source' => ['?filters[source]=tcxFile', ['queued.gpx', 'broken.tcx']];
        yield 'filters combine' => ['?filters[filename]=ride.fit&filters[status]=success', ['queued.gpx', 'ride.fit']];
    }

    #[DataProvider('providePaginationScenarios')]
    public function testItPaginatesAcrossQueuedFilesAndImports(string $queryString, array $expectedFilenames): void
    {
        $this->seedFileImports();

        $this->client->request('GET', self::PATH.$queryString, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $response = Json::decode((string) $this->client->getResponse()->getContent());
        $this->assertSame($expectedFilenames, array_column($response['fileImports'], 'filename'));
        $this->assertSame(4, $response['pagination']['total']);
    }

    public static function providePaginationScenarios(): iterable
    {
        yield 'a page starting with the queued file' => ['?pagination[page]=1&pagination[size]=2', ['queued.gpx', 'ride.fit']];
        yield 'a page holding only imports' => ['?pagination[page]=2&pagination[size]=2', ['broken.tcx', 'ride.fit']];
    }

    #[DataProvider('provideInvalidQueryParameters')]
    public function testItRejectsInvalidQueryParameters(string $queryString): void
    {
        $this->client->request('GET', self::PATH.$queryString, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame(
            'bad_request',
            Json::decode((string) $this->client->getResponse()->getContent())['error']
        );
    }

    public static function provideInvalidQueryParameters(): iterable
    {
        yield 'an unknown status' => ['?filters[status]=done'];
        yield 'the queued status' => ['?filters[status]=queued'];
        yield 'an unknown source' => ['?filters[source]=pdfFile'];
        yield 'a source that is not file based' => ['?filters[source]=stravaApi'];
        yield 'a page size above the maximum' => ['?pagination[size]=101'];
    }

    private function seedFileImports(): void
    {
        $fileImportRepository = static::getContainer()->get(FileImportRepository::class);
        $fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withOriginalFilename('ride.fit')
                ->withSource(ImportSource::FIT_FILE)
                ->withStatus(FileImportStatus::SKIPPED)
                ->withErrorMessage('Skipped, activity was already imported')
                ->withImportedOn(SerializableDateTime::fromString('2026-06-01 08:00:00'))
                ->build()
        );
        $fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('2'))
                ->withOriginalFilename('broken.tcx')
                ->withSource(ImportSource::TCX_FILE)
                ->withStatus(FileImportStatus::FAILED)
                ->withErrorMessage('Could not parse file')
                ->withImportedOn(SerializableDateTime::fromString('2026-06-02 08:00:00'))
                ->build()
        );
        $fileImportRepository->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('3'))
                ->withOriginalFilename('ride.fit')
                ->withSource(ImportSource::FIT_FILE)
                ->withStatus(FileImportStatus::SUCCESS)
                ->withActivityId(ActivityId::fromUnprefixed('42'))
                ->withImportedOn(SerializableDateTime::fromString('2026-06-03 08:00:00'))
                ->build()
        );

        static::getContainer()->get(FilesystemOperator::class)->write('watch/queued.gpx', 'raw gpx bytes');
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
    }
}
