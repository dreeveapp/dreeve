<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin\Settings;

use App\Domain\Import\ImportMode;
use App\Tests\Controller\Admin\AdminWebTestCase;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DaemonSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testItRendersTheDaemonSettingsPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/daemon');

        $this->assertResponseIsSuccessful();

        // The daemon settings form.
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="daemon"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[cron][runStravaImportAndBuildApp][expression]"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[cron][runStravaImportAndBuildApp][enabled]"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[cron][gearMaintenanceNotification][expression]"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[cron][appUpdateAvailableNotification][expression]"]'));
        $this->assertStringContainsString(
            'Changes to these settings only take effect after you restart the daemon container.',
            $crawler->filter('form[data-dispatch-command="update-settings"]')->text(),
        );

        // No backup yet, so there is nothing to download.
        $this->assertCount(0, $crawler->filter('a[href="/admin/backups/latest/download"]'));
        $this->assertStringContainsString('There is no backup yet.', $crawler->filter('form[data-dispatch-command="update-settings"]')->text());

        // The settings navigation, with "Daemon" active.
        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('Daemon', $selectedLink->text());
    }

    public function testItHidesTheStravaImportActionInFileImportMode(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/daemon');

        $this->assertResponseIsSuccessful();
        // The Strava import action is not supported in file import mode.
        $this->assertCount(0, $crawler->filter('input[name="data[cron][runStravaImportAndBuildApp][expression]"]'));
        // The notification actions are supported in every import mode.
        $this->assertCount(1, $crawler->filter('input[name="data[cron][gearMaintenanceNotification][expression]"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[cron][appUpdateAvailableNotification][expression]"]'));
    }

    public function testItOffersTheLatestBackupForDownload(): void
    {
        /** @var FilesystemOperator $defaultStorage */
        $defaultStorage = static::getContainer()->get('default.storage');
        $defaultStorage->write('backups/dreeve-2026-11-07-030000.tar.zst', 'older backup');
        $defaultStorage->write('backups/dreeve-2026-11-08-030000.tar.zst', 'latest backup');
        $defaultStorage->write('backups/dreeve-2026-11-09-030000.tar.zst', 'backup that is still being written');
        $defaultStorage->write('backups/dreeve-2026-11-09-030000/database/dreeve.db', 'database snapshot');
        $defaultStorage->write('backups/notes.txt', 'not a backup');
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/daemon');

        $this->assertResponseIsSuccessful();
        $this->assertSame('dreeve-2026-11-08-030000.tar.zst (0.0 MB)', $crawler->filter('input#latestBackup')->attr('value'));
        $this->assertCount(1, $crawler->filter('a[href="/admin/backups/latest/download"]'));
        $this->assertStringNotContainsString('There is no backup yet.', $crawler->filter('form[data-dispatch-command="update-settings"]')->text());

        $this->client->request('GET', '/admin/backups/latest/download');

        $this->assertResponseIsSuccessful();
        $this->assertSame('latest backup', $this->client->getInternalResponse()->getContent());
        $this->assertResponseHeaderSame('Content-Type', 'application/zstd');
        $this->assertResponseHeaderSame('Content-Length', '13');
        $this->assertResponseHeaderSame('Content-Disposition', 'attachment; filename=dreeve-2026-11-08-030000.tar.zst');
    }

    public function testItCannotDownloadABackupWhenThereIsNone(): void
    {
        $this->client->loginUser($this->adminUser());
        $this->client->catchExceptions(false);

        $this->expectExceptionObject(new NotFoundHttpException('There is no backup to download'));
        $this->client->request('GET', '/admin/backups/latest/download');
    }
}
