<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

class GeneralSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testItRendersTheGeneralSettingsPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/general');

        $this->assertResponseIsSuccessful();

        // The general settings form.
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="general"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[birthday]"]'));
        $this->assertCount(1, $crawler->filter('input[name="data[firstName]"][required]'));
        $this->assertCount(1, $crawler->filter('input[name="data[lastName]"][required]'));

        // Four repeaters: max heart rate ranges, resting heart rate ranges, FTP cycling, FTP running.
        $this->assertCount(4, $crawler->filter('form[data-dispatch-command="update-settings"] [data-repeater]'));
        $this->assertCount(0, $crawler->filter('[name^="data[weightHistory]"]'));

        // The heart rate zones editor.
        $this->assertCount(1, $crawler->filter('select[name="data[heartRateZones][mode]"]'));
        // Five default zone rows, pre-filled since the baseline has no custom zones.
        $this->assertCount(5, $crawler->filter('input[name^="data[heartRateZones][zones]"][name$="[from]"]'));
        $this->assertCount(1, $crawler->filter('textarea[name="data[heartRateZones][advanced]"]'));

        // The settings navigation, with "General" active.
        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $this->assertCount(1, $settingsPanel);
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('General', $selectedLink->text());
    }
}
