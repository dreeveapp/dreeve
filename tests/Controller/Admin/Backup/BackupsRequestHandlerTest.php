<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin\Backup;

use App\Domain\Backup\AvailableBackups;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\Controller\Admin\AdminWebTestCase;
use League\Flysystem\FilesystemOperator;

class BackupsRequestHandlerTest extends AdminWebTestCase
{
    public function testItRendersTheEmptyStateWhenThereAreNoBackups(): void
    {
        static::getContainer()->set(AvailableBackups::class, new AvailableBackups(
            defaultStorage: static::getContainer()->get('default.storage'),
            kernelProjectDir: KernelProjectDir::fromString(sys_get_temp_dir()),
            queryBus: static::getContainer()->get(QueryBus::class),
        ));
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/backups');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('table.data-table tbody td[colspan="6"]'));
        $this->assertStringContainsString('No backups yet.', $crawler->filter('table.data-table')->text());
        $this->assertCount(1, $crawler->filter('table.data-table a.btn--primary[href="/admin/settings/daemon"]'));
        $this->assertCount(1, $crawler->filter('table.data-table a[href="https://docs.dreeve.app/maintenance/backup-and-restore/#make-a-backup"]'));
        $this->assertCount(1, $crawler->filter('#drawer-navigation a[href="/admin/backups"][aria-selected="true"]'));
        $this->assertCount(1, $crawler->filter('.alert--warning a[href="https://docs.dreeve.app/maintenance/backup-and-restore/#mount-the-backups-directory"]'));
    }

    public function testItExplainsAutomaticBackupsInTheEmptyStateOnManagedInstances(): void
    {
        $this->withManagedMode();
        static::getContainer()->set(AvailableBackups::class, new AvailableBackups(
            defaultStorage: static::getContainer()->get('default.storage'),
            kernelProjectDir: KernelProjectDir::fromString(sys_get_temp_dir()),
            queryBus: static::getContainer()->get(QueryBus::class),
        ));
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/backups');

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            'No backups yet. A backup is created automatically every night. Read more in the documentation.',
            $crawler->filter('table.data-table tbody td[colspan="6"]')->text(),
        );
        $this->assertCount(0, $crawler->filter('table.data-table a.btn--primary'));
        $this->assertCount(1, $crawler->filter('table.data-table a[href="https://docs.dreeve.app/maintenance/backup-and-restore/#make-a-backup"]'));
    }

    public function testItListsTheBackupsNewestFirstAndDownloadsOne(): void
    {
        /** @var FilesystemOperator $defaultStorage */
        $defaultStorage = static::getContainer()->get('default.storage');
        $defaultStorage->write('backups/dreeve-2026-11-07-030000.tar.zst', 'older backup');
        $defaultStorage->write('backups/dreeve-2026-11-08-030000.tar.zst', 'latest backup');
        $defaultStorage->write('backups/dreeve-2026-11-08-030000.json', '{"appVersion": "v5.8.2", "numberOfActivities": 1234, "firstActivityStartDate": "2015-03-01", "lastActivityStartDate": "2026-11-07"}');
        $defaultStorage->write('backups/dreeve-2026-11-09-030000.tar.zst.partial', 'backup that is still being written');
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/backups');

        $this->assertResponseIsSuccessful();
        $rows = $crawler->filter('table.data-table tbody tr');
        $this->assertCount(2, $rows);
        $this->assertSame('08-11-26 03:00:00 dreeve-2026-11-08-030000.tar.zst 0.0MB 1234 01-03-15 - 07-11-26 v5.8.2 Download backup', $rows->eq(0)->text());
        $this->assertSame('07-11-26 03:00:00 dreeve-2026-11-07-030000.tar.zst 0.0MB - - Download backup', $rows->eq(1)->text());
        $this->assertSame(
            '/admin/backups/dreeve-2026-11-07-030000.tar.zst/download',
            $rows->eq(1)->filter('a[title="Download backup"]')->attr('href'),
        );
        $this->assertStringNotContainsString('No backups yet.', $crawler->filter('table.data-table')->text());

        $this->client->request('GET', '/admin/backups/dreeve-2026-11-07-030000.tar.zst/download');

        $this->assertResponseIsSuccessful();
        $this->assertSame('older backup', $this->client->getInternalResponse()->getContent());
        $this->assertResponseHeaderSame('Content-Type', 'application/zstd');
        $this->assertResponseHeaderSame('Content-Length', '12');
        $this->assertResponseHeaderSame('Content-Disposition', 'attachment; filename=dreeve-2026-11-07-030000.tar.zst');
    }

    public function testItCannotDownloadABackupThatDoesNotExist(): void
    {
        $this->client->loginUser($this->adminUser());
        $this->client->catchExceptions(false);

        $this->expectExceptionObject(new EntityNotFound('Backup "dreeve-2026-11-07-030000.tar.zst" not found'));
        $this->client->request('GET', '/admin/backups/dreeve-2026-11-07-030000.tar.zst/download');
    }
}
