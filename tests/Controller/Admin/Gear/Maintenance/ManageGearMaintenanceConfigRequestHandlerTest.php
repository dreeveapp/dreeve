<?php

namespace App\Tests\Controller\Admin\Gear\Maintenance;

use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\ProvideGearMaintenanceConfig;

class ManageGearMaintenanceConfigRequestHandlerTest extends AdminWebTestCase
{
    use ProvideGearMaintenanceConfig;

    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/gear/maintenance-config');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRendersTheConfigReflectingTheCurrentSettings(): void
    {
        $this->importGearMaintenanceConfig();

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/maintenance-config');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Gear maintenance', $crawler->filter('body')->text());
        $this->assertStringContainsString('Components', $crawler->filter('body')->text());
        $this->assertCount(1, $crawler->filter('table.data-table'));
        $this->assertStringContainsString('Save settings', $crawler->filter('button[type="submit"]')->text());

        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-gear-maintenance-config"]'));

        $this->assertCount(1, $crawler->filter('#enabled[checked]'));
        $this->assertCount(1, $crawler->filter('#ignoreRetiredGear[checked]'));

        $this->assertCount(0, $crawler->filter('#countersResetMode'));

        $this->assertCount(2, $crawler->filter('table.data-table tbody tr'));
        $tableText = $crawler->filter('table.data-table')->text();
        $this->assertStringContainsString('Some cool chain', $tableText);
        $this->assertStringContainsString('DI2 Battery', $tableText);
        $this->assertStringNotContainsString('No components yet.', $tableText);

        $this->assertCount(1, $crawler->filter('.alert.alert--warning'));
        $warningText = $crawler->filter('.alert.alert--warning')->text();
        $this->assertStringContainsString('is attached to one of your components, but does not exist', $warningText);
    }

    public function testItRendersTheEmptyStateWhenThereAreNoComponents(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/maintenance-config');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('No components yet.', $crawler->filter('table.data-table')->text());
        $this->assertCount(1, $crawler->filter('table.data-table tbody td[colspan="5"]'));
        $this->assertCount(0, $crawler->filter('.alert.alert--warning'));
    }
}
