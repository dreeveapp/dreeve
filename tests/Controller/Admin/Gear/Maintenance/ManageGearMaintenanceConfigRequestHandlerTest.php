<?php

namespace App\Tests\Controller\Admin\Gear\Maintenance;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\ProvideGearMaintenanceConfig;

class ManageGearMaintenanceConfigRequestHandlerTest extends AdminWebTestCase
{
    use ProvideGearMaintenanceConfig;

    public function testItRendersTheConfigReflectingTheCurrentSettings(): void
    {
        $this->importGearMaintenanceConfig();
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('g10130856'))
                ->build()
        );

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
        $this->assertStringContainsString('Gear "g1233776" is attached to one of your components, but does not exist', $warningText);
        $this->assertStringNotContainsString('10130856', $warningText);
    }

    public function testItMarksComponentsThatAreNotFitted(): void
    {
        $this->getContainer()->get(KeyValueStore::class)->save(KeyValue::fromState(
            key: Key::GEAR_MAINTENANCE,
            value: Value::fromString((string) file_get_contents(__DIR__.'/fixtures/gear-maintenance-with-not-fitted-component.json')),
        ));
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('g10130856'))
                ->withName('Road bike')
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/maintenance-config');

        $this->assertResponseIsSuccessful();
        $rows = $crawler->filter('table.data-table tbody tr');
        $this->assertStringContainsString('Summer wheelset', $rows->eq(0)->text());
        $this->assertCount(1, $rows->eq(0)->filter('.pill--warning'));
        $this->assertStringNotContainsString('Road bike', $rows->eq(0)->text());
        $this->assertStringContainsString('Winter wheelset', $rows->eq(1)->text());
        $this->assertCount(0, $rows->eq(1)->filter('.pill--warning'));
        $this->assertStringContainsString('Road bike', $rows->eq(1)->text());
        $this->assertStringContainsString('Old chain', $rows->eq(2)->text());
        $this->assertCount(1, $rows->eq(2)->filter('.pill--danger'));
        $this->assertCount(0, $rows->eq(2)->filter('.pill--warning'));
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

    public function testSavingReturnsTheVisitorToThePublicMaintenancePageTheyCameFrom(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/maintenance-config?redirectTo=%2Fgear%2Fmaintenance');

        $this->assertResponseIsSuccessful();
        $this->assertSame('/gear/maintenance', $crawler->filter('form[data-dispatch-command]')->attr('data-redirect'));
        $this->assertSame(
            '/gear/maintenance',
            $crawler->filter('nav a:has(span:contains("Return to app"))')->attr('href'),
        );
    }

    public function testSavingWithoutARedirectToKeepsTheVisitorInTheAdminPanel(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/maintenance-config');

        $this->assertResponseIsSuccessful();
        $this->assertSame('', $crawler->filter('form[data-dispatch-command]')->attr('data-redirect'));
    }
}
