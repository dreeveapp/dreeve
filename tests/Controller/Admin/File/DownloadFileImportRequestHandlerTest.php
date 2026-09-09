<?php

namespace App\Tests\Controller\Admin\File;

use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Domain\Import\FileImportStatus;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Import\FileImportBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DownloadFileImportRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/file-imports/'.FileImportId::random().'/download');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testDownloadsTheOriginalFile(): void
    {
        $fileImportId = FileImportId::fromUnprefixed('1');
        $fileContents = $this->fixture('activity.fit');

        static::getContainer()->get(FileImportRepository::class)->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId($fileImportId)
                ->withOriginalFilename('morning-run.fit')
                ->withFileContents($fileContents)
                ->build()
        );

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/admin/file-imports/'.$fileImportId.'/download');

        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertSame($fileContents, $response->getContent());
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename=morning-run.fit', $response->headers->get('Content-Disposition'));
    }

    public function testFallsBackToAnAsciiFilename(): void
    {
        $fileImportId = FileImportId::fromUnprefixed('1');

        static::getContainer()->get(FileImportRepository::class)->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId($fileImportId)
                ->withOriginalFilename('ochtendloop-é.gpx')
                ->withFileContents('raw gpx bytes')
                ->build()
        );

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/admin/file-imports/'.$fileImportId.'/download');

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            "attachment; filename=ochtendloop-__.gpx; filename*=utf-8''ochtendloop-%C3%A9.gpx",
            $this->client->getResponse()->headers->get('Content-Disposition')
        );
    }

    public function testCannotDownloadAnImportThatNeverStoredItsFile(): void
    {
        $fileImportId = FileImportId::fromUnprefixed('1');

        static::getContainer()->get(FileImportRepository::class)->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId($fileImportId)
                ->withFileContents(null)
                ->withStatus(FileImportStatus::SKIPPED)
                ->build()
        );

        $this->client->loginUser($this->adminUser());
        $this->client->catchExceptions(false);

        $this->expectExceptionObject(new NotFoundHttpException(sprintf('File import "%s" has no stored file contents', $fileImportId)));
        $this->client->request('GET', '/admin/file-imports/'.$fileImportId.'/download');
    }

    public function testCannotDownloadAnUnknownFileImport(): void
    {
        $fileImportId = FileImportId::fromUnprefixed('nope');

        $this->client->loginUser($this->adminUser());
        $this->client->catchExceptions(false);

        $this->expectExceptionObject(new NotFoundHttpException(sprintf('File import "%s" not found', $fileImportId)));
        $this->client->request('GET', '/admin/file-imports/'.$fileImportId.'/download');
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(static::getContainer()->get(KernelProjectDir::class).'/tests/Domain/Import/FileParser/fixtures/'.$name);
        if (false === $contents) {
            self::fail(sprintf('Could not read fixture "%s"', $name));
        }

        return $contents;
    }
}
