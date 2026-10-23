<?php

namespace App\Tests\Controller\Api\V1\FileImport;

use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Domain\Import\FileImportStatus;
use App\Infrastructure\Security\Token\ApiToken;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Import\FileImportBuilder;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\Response;

class FileImportFileRequestHandlerTest extends ControllerWebTestCase
{
    private ApiToken $token;

    public function testItDownloadsTheStoredFile(): void
    {
        static::getContainer()->get(FileImportRepository::class)->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withOriginalFilename('morning-ride.fit')
                ->withFileContents('raw fit bytes')
                ->build()
        );

        $this->client->request(
            'GET',
            '/api/v1/file-imports/fileImport-1/file',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/octet-stream');
        $this->assertResponseHeaderSame('Content-Disposition', 'attachment; filename=morning-ride.fit');
        $this->assertSame('raw fit bytes', $this->client->getResponse()->getContent());
    }

    public function testItReportsWhenAFileImportHasNoStoredFile(): void
    {
        static::getContainer()->get(FileImportRepository::class)->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withFileContents(null)
                ->withStatus(FileImportStatus::SKIPPED)
                ->build()
        );

        $this->client->request(
            'GET',
            '/api/v1/file-imports/fileImport-1/file',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame([
            'error' => 'file_not_available',
            'message' => 'File import "fileImport-1" has no stored file.',
        ], Json::decode((string) $this->client->getResponse()->getContent()));
    }

    #[TestWith(['fileImport-1'])]
    #[TestWith(['not-a-file-import-id'])]
    public function testItReportsAnUnknownOrMalformedFileImportAsNotFound(string $fileImportId): void
    {
        $this->client->request(
            'GET',
            '/api/v1/file-imports/'.$fileImportId.'/file',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $this->assertSame(
            'not_found',
            Json::decode((string) $this->client->getResponse()->getContent())['error']
        );
    }

    #[\Override]
    protected function prepareEnvironment(): void
    {
        $this->token = ApiToken::generate();
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
    }
}
