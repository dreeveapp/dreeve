<?php

namespace App\Tests\Controller\Admin\Gear\Maintenance;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\ProvideGearMaintenanceConfig;

class ManageGearMaintenanceLogOverviewRequestHandlerTest extends AdminWebTestCase
{
    use ProvideGearMaintenanceConfig;

    public function testRendersTheEmptyStateWhenThereAreNoLogs(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/maintenance-logs');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('table.data-table'));
        $this->assertStringContainsString('No maintenance logs yet.', $crawler->filter('body')->text());
        $this->assertCount(1, $crawler->filter('table.data-table tbody td[colspan="5"]'));
        $this->assertStringContainsString('Register maintenance', $crawler->filter('a.btn--add')->text());
    }

    public function testRendersTheTableWithLogs(): void
    {
        $this->importGearMaintenanceConfig();

        static::getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('g10130856'))
                ->withName('Race bike')
                ->build()
        );

        static::getContainer()->get(GearMaintenanceLogRepository::class)->add(
            GearMaintenanceLog::create(
                gearId: GearId::fromUnprefixed('g10130856'),
                maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
                performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
            )
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/maintenance-logs');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('table.data-table tbody tr'));
        $this->assertStringNotContainsString('No maintenance logs yet.', $crawler->filter('body')->text());

        $rowText = $crawler->filter('table.data-table tbody tr')->text();
        $this->assertStringContainsString('Race bike', $rowText);
        $this->assertStringContainsString('Some cool chain', $rowText);
        $this->assertStringContainsString('Lube', $rowText);

        $this->assertCount(1, $crawler->filter('table.data-table tbody a[title="Edit"]'));
        $this->assertCount(1, $crawler->filter('table.data-table tbody a[title="Delete"]'));
    }
}
