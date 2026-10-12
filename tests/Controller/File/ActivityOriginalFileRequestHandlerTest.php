<?php

namespace App\Tests\Controller\File;

use App\Domain\Activity\ActivityId;
use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Domain\Import\FileImportStatus;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Import\FileImportBuilder;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ActivityOriginalFileRequestHandlerTest extends AdminWebTestCase
{
    public function testAnAdminDownloadsTheOriginalFile(): void
    {
        static::getContainer()->get(FileImportRepository::class)->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withOriginalFilename('morning-run.fit')
                ->withFileContents('raw fit bytes')
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->build()
        );

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/activities/activity-1/original-file');

        $response = $this->client->getResponse();
        $this->assertResponseIsSuccessful();
        $this->assertSame('raw fit bytes', $response->getContent());
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename=morning-run.fit', $response->headers->get('Content-Disposition'));
    }

    public function testAVisitorCannotDownloadTheOriginalFileByDefault(): void
    {
        static::getContainer()->get(FileImportRepository::class)->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withFileContents('raw fit bytes')
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->build()
        );

        $this->client->catchExceptions(false);

        $this->expectExceptionObject(new NotFoundHttpException('Not found'));
        $this->client->request('GET', '/activities/activity-1/original-file');
    }

    public function testAVisitorDownloadsTheOriginalFileWhenTheSettingAllowsIt(): void
    {
        static::getContainer()->get(SettingsRepository::class)->saveGroup(SettingsGroup::SECURITY, [
            'visitorsCanDownloadOriginalFiles' => '1',
        ]);
        static::getContainer()->get(FileImportRepository::class)->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withOriginalFilename('morning-run.fit')
                ->withFileContents('raw fit bytes')
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->build()
        );

        $this->client->request('GET', '/activities/activity-1/original-file');

        $this->assertResponseIsSuccessful();
        $this->assertSame('raw fit bytes', $this->client->getResponse()->getContent());
    }

    public function testCannotDownloadWhenTheImportNeverStoredItsFile(): void
    {
        static::getContainer()->get(FileImportRepository::class)->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId(FileImportId::fromUnprefixed('1'))
                ->withFileContents(null)
                ->withStatus(FileImportStatus::SKIPPED)
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->build()
        );

        $this->client->loginUser($this->adminUser());
        $this->client->catchExceptions(false);

        $this->expectExceptionObject(new NotFoundHttpException('Activity "activity-1" has no original file'));
        $this->client->request('GET', '/activities/activity-1/original-file');
    }

    public function testCannotDownloadForAnActivityWithoutAFileImport(): void
    {
        $this->client->loginUser($this->adminUser());
        $this->client->catchExceptions(false);

        $this->expectExceptionObject(new NotFoundHttpException('Activity "activity-nope" has no original file'));
        $this->client->request('GET', '/activities/activity-nope/original-file');
    }
}
