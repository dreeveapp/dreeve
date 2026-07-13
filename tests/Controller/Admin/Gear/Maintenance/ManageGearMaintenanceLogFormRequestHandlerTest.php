<?php

namespace App\Tests\Controller\Admin\Gear\Maintenance;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogId;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\ProvideGearMaintenanceConfig;

class ManageGearMaintenanceLogFormRequestHandlerTest extends AdminWebTestCase
{
    use ProvideGearMaintenanceConfig;

    public function testAnonymousUsersAreRedirectedToTheLoginPageOnAdd(): void
    {
        $this->client->request('GET', '/admin/gear/maintenance-logs/register');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testAnonymousUsersAreRedirectedToTheLoginPageOnEdit(): void
    {
        $this->client->request('GET', '/admin/gear/maintenance-logs/'.GearMaintenanceLogId::random().'/edit');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testAnonymousUsersAreRedirectedToTheLoginPageOnDelete(): void
    {
        $this->client->request('GET', '/admin/gear/maintenance-logs/'.GearMaintenanceLogId::random().'/delete');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testRendersTheDeleteConfirmation(): void
    {
        $this->seedConfigAndGear();

        $log = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('g10130856'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        static::getContainer()->get(GearMaintenanceLogRepository::class)->add($log);

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/maintenance-logs/'.$log->getId().'/delete');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Delete maintenance log', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="delete-gear-maintenance-log"]');
        $this->assertCount(1, $form);
        $this->assertSame((string) $log->getId(), $form->filter('input[name="gearMaintenanceLogId"]')->attr('value'));
        $this->assertCount(1, $form->filter('button.btn--danger'));

        $formText = $form->text();
        $this->assertStringContainsString('Race bike', $formText);
        $this->assertStringContainsString('Some cool chain', $formText);
        $this->assertStringContainsString('Lube', $formText);
    }

    public function testCannotDeleteALogWhoseGearWasDeleted(): void
    {
        $this->importGearMaintenanceConfig();
        $log = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('g999'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        static::getContainer()->get(GearMaintenanceLogRepository::class)->add($log);

        $this->client->loginUser($this->adminUser());
        $this->client->catchExceptions(false);

        $this->expectExceptionObject(new EntityNotFound(sprintf('Gear maintenance log "%s" is no longer available', $log->getId())));
        $this->client->request('GET', '/admin/gear/maintenance-logs/'.$log->getId().'/delete');
    }

    public function testRendersTheRegisterForm(): void
    {
        $this->seedConfigAndGear();

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/maintenance-logs/register');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Register maintenance', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="add-gear-maintenance-log"]');
        $this->assertCount(1, $form);

        $this->assertCount(1, $form->filter('select[name="maintenanceTaskId"]'));
        $this->assertCount(1, $form->filter('select[name="gearId"]'));
        $this->assertCount(1, $form->filter('input[name="performedOn"][type="date"]'));

        $this->assertGreaterThan(1, $form->filter('select#gml-component option')->count());
        $this->assertSame('gml-component', $form->filter('select[name="maintenanceTaskId"]')->attr('data-depends-on'));
        $this->assertSame('gml-component', $form->filter('select[name="gearId"]')->attr('data-depends-on'));

        $this->assertGreaterThan(0, $form->filter('select[name="maintenanceTaskId"] option[data-when]')->count());
        $this->assertStringContainsString('Lube', $form->filter('select[name="maintenanceTaskId"]')->text());
        $this->assertStringContainsString('Race bike', $form->filter('select[name="gearId"]')->text());
    }

    public function testRendersTheEditFormPrefilledWithTheLog(): void
    {
        $this->seedConfigAndGear();

        $log = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('g10130856'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        static::getContainer()->get(GearMaintenanceLogRepository::class)->add($log);

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/maintenance-logs/'.$log->getId().'/edit');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Edit maintenance log', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="update-gear-maintenance-log"]');
        $this->assertCount(1, $form);

        $this->assertSame((string) $log->getId(), $form->filter('input[name="gearMaintenanceLogId"]')->attr('value'));
        $this->assertSame('2025-01-01', $form->filter('input[name="performedOn"]')->attr('value'));

        $this->assertCount(0, $form->filter('select[name="gearId"]'));
        $this->assertCount(0, $form->filter('select[name="maintenanceTaskId"]'));
        $formText = $form->text();
        $this->assertStringContainsString('Race bike', $formText);
        $this->assertStringContainsString('Some cool chain', $formText);
        $this->assertStringContainsString('Lube', $formText);
    }

    public function testCannotEditALogWhoseGearWasDeleted(): void
    {
        $this->importGearMaintenanceConfig();
        $log = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('g999'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        static::getContainer()->get(GearMaintenanceLogRepository::class)->add($log);

        $this->client->loginUser($this->adminUser());
        $this->client->catchExceptions(false);

        $this->expectExceptionObject(new EntityNotFound(sprintf('Gear maintenance log "%s" is no longer available', $log->getId())));
        $this->client->request('GET', '/admin/gear/maintenance-logs/'.$log->getId().'/edit');
    }

    public function testCannotEditALogWhoseMaintenanceTaskWasDeleted(): void
    {
        $this->seedConfigAndGear();
        $log = GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('g10130856'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-removed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        );
        static::getContainer()->get(GearMaintenanceLogRepository::class)->add($log);

        $this->client->loginUser($this->adminUser());
        $this->client->catchExceptions(false);

        $this->expectExceptionObject(new EntityNotFound(sprintf('Gear maintenance log "%s" is no longer available', $log->getId())));
        $this->client->request('GET', '/admin/gear/maintenance-logs/'.$log->getId().'/edit');
    }

    private function seedConfigAndGear(): void
    {
        $this->importGearMaintenanceConfig();
        static::getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('g10130856'))
                ->withName('Race bike')
                ->build()
        );
    }
}
