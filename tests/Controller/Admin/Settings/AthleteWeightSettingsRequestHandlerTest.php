<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

class AthleteWeightSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testItRendersTheWeightHistorySettingsPage(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/athlete-weight');

        $this->assertResponseIsSuccessful();

        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] input[name="group"][value="athleteWeight"]'));
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"] [data-repeater]'));
        $this->assertSame('Weigh-ins', $crawler->filter('section h3')->text());
        $this->assertStringContainsString('Needed to calculate relative w/kg.', $crawler->filter('section p.description')->text());
        $this->assertSame(
            [
                ['on' => '2020-01-01', 'weight' => 68],
                ['on' => '2019-12-01', 'weight' => 69],
                ['on' => '2019-08-01', 'weight' => 70],
                ['on' => '2019-07-01', 'weight' => 71],
            ],
            json_decode((string) $crawler->filter('[data-repeater-list]')->attr('data-repeater-initial'), true),
        );
        $this->assertCount(1, $crawler->filter('template[data-repeater-template]'));
        $this->assertStringContainsString('data[weightHistory][__index__][weight]', $crawler->filter('template[data-repeater-template]')->html());

        $settingsPanel = $crawler->filter('nav.contextual-panel[aria-label="Settings"]');
        $selectedLink = $settingsPanel->filter('a[aria-selected="true"]');
        $this->assertCount(1, $selectedLink);
        $this->assertStringContainsString('Weight history', $selectedLink->text());
    }
}
