<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

class AthleteFtpSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testItRendersTheFtpHistorySettingsPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/athlete-ftp');

        $this->assertResponseIsSuccessful();

        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="athleteFtp"]'));
        $this->assertCount(2, $crawler->filter('form[data-dispatch-command="update-settings"] [data-repeater]'));
        $this->assertSame(
            [
                ['on' => '2023-01-01', 'ftp' => 198],
                ['on' => '2023-03-22', 'ftp' => 220],
                ['on' => '2023-03-29', 'ftp' => 238],
                ['on' => '2023-04-01', 'ftp' => 250],
            ],
            json_decode((string) $crawler->filter('[data-repeater-list]')->first()->attr('data-repeater-initial'), true),
        );
        $this->assertStringContainsString('data[ftpHistory][running][__index__][ftp]', $crawler->filter('template[data-repeater-template]')->last()->html());

        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('FTP history', $selectedLink->text());
    }
}
