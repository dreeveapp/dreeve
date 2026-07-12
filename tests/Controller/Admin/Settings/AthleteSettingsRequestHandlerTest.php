<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;

class AthleteSettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/athlete');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersTheAthleteForm(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/athlete');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-athlete-settings"]'));

        $this->assertCount(0, $crawler->filter('input[name="group"]'));
        $this->assertCount(1, $crawler->filter('input[name="athlete[firstName]"]'));
        $this->assertCount(1, $crawler->filter('input[name="athlete[lastName]"]'));
        $this->assertCount(1, $crawler->filter('input[name="athlete[birthday]"]'));
        $this->assertCount(1, $crawler->filter('select[name="athlete[gender]"]'));
        $this->assertCount(1, $crawler->filter('select[name="athlete[maxHeartRateFormula]"]'));
        $this->assertCount(1, $crawler->filter('input[name="athlete[restingHeartRateFormula]"]'));

        // Every other admin page is off limits until the athlete has been configured.
        $this->assertCount(0, $crawler->filter('#drawer-navigation'));
        $this->assertCount(0, $crawler->filter('nav.contextual-panel'));
        $this->assertCount(0, $crawler->filter('[data-drawer-toggle="drawer-navigation"]'));
        $this->assertStringNotContainsString('Return to app', $crawler->filter('body')->text());
    }
}
