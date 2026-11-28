<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

class NotificationsSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testItRendersTheNotificationsSettingsPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/notifications');

        $this->assertResponseIsSuccessful();

        // The notifications settings form.
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="notifications"]'));
        $this->assertCount(1, $crawler->filter('template[data-repeater-template] input[name="data[notifications][services][]"]'));
        $this->assertCount(1, $crawler->filter('input[type="checkbox"][name="data[notifications][notifyOnSuccessfulBuild]"][checked]'));
        $this->assertCount(1, $crawler->filter('input[type="hidden"][name="data[notifications][notifyOnSuccessfulBuild]"][value="0"]'));
        $this->assertCount(1, $crawler->filter('a[href="https://shoutrrr.nickfedor.com/dev/services/overview/"]'));

        // The settings navigation, with "Notifications" active.
        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertSame('Notifications', $selectedLink->text());
    }
}
