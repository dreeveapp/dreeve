<?php

namespace App\Tests\Controller\Admin\Gear\Maintenance;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\ProvideGearMaintenanceConfig;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ManageGearMaintenanceComponentFormRequestHandlerTest extends AdminWebTestCase
{
    use ProvideGearMaintenanceConfig;

    public function testAnonymousUsersAreRedirectedToTheLoginPageOnAdd(): void
    {
        $this->client->request('GET', '/admin/gear/maintenance-config/component/add');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testAnonymousUsersAreRedirectedToTheLoginPageOnEdit(): void
    {
        $this->client->request('GET', '/admin/gear/maintenance-config/component/gearComponent-chain/edit');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testAnonymousUsersAreRedirectedToTheLoginPageOnDelete(): void
    {
        $this->client->request('GET', '/admin/gear/maintenance-config/component/gearComponent-chain/delete');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testRendersTheAddForm(): void
    {
        $this->seedGears();

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/maintenance-config/component/add');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Add component', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="create-gear-maintenance-component"]');
        $this->assertCount(1, $form);

        $this->assertCount(0, $form->filter('input[name="gearComponentId"]'));
        $this->assertSame('', $form->filter('input[name="label"]')->attr('value'));

        $this->assertCount(2, $form->filter('input[name="attachedTo[]"]'));
        $this->assertCount(0, $form->filter('input[name="attachedTo[]"][checked]'));
        $this->assertStringContainsString('Race bike', $form->text());

        $this->assertCount(1, $form->filter('input[name="purchasePriceAmount"]'));
        $this->assertCount(1, $form->filter('select[name="purchasePriceCurrency"]'));

        $repeater = $form->filter('[data-repeater]');
        $this->assertCount(1, $repeater);
        $this->assertSame('1', $repeater->attr('data-repeater-min'));
        $this->assertCount(1, $form->filter('template[data-repeater-template]'));
        $this->assertSame('[]', $form->filter('[data-repeater-list]')->attr('data-repeater-initial'));
    }

    public function testRendersTheEditFormPrefilledWithTheComponent(): void
    {
        $this->importGearMaintenanceConfig();
        $this->seedGears();

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/maintenance-config/component/gearComponent-chain/edit');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Edit component', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="update-gear-maintenance-component"]');
        $this->assertCount(1, $form);

        $this->assertSame('gearComponent-chain', $form->filter('input[name="gearComponentId"]')->attr('value'));
        $this->assertSame('Some cool chain', $form->filter('input[name="label"]')->attr('value'));

        $this->assertGreaterThanOrEqual(1, $form->filter('input[name="attachedTo[]"][checked]')->count());

        $initialTasks = $form->filter('[data-repeater-list]')->attr('data-repeater-initial');
        $this->assertStringContainsString('Lube', $initialTasks);
        $this->assertStringContainsString('chain-lubed', $initialTasks);

        $this->assertStringContainsString(
            'chain.png',
            $form->filter('[data-image-dropzone]')->attr('data-existing-images')
        );
    }

    public function testRendersTheDeleteConfirmation(): void
    {
        $this->importGearMaintenanceConfig();

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/maintenance-config/component/gearComponent-chain/delete');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Delete component', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="delete-gear-maintenance-component"]');
        $this->assertCount(1, $form);
        $this->assertSame('gearComponent-chain', $form->filter('input[name="gearComponentId"]')->attr('value'));
        $this->assertCount(1, $form->filter('button.btn--danger'));
        $this->assertStringContainsString('Some cool chain', $form->text());
    }

    public function testReturns404WhenEditingAnUnknownComponent(): void
    {
        $this->importGearMaintenanceConfig();

        $this->client->loginUser($this->adminUser());
        $this->client->catchExceptions(false);

        $this->expectExceptionObject(new NotFoundHttpException('Component not found'));
        $this->client->request('GET', '/admin/gear/maintenance-config/component/gearComponent-does-not-exist/edit');
    }

    public function testReturns404WhenDeletingAnUnknownComponent(): void
    {
        $this->importGearMaintenanceConfig();

        $this->client->loginUser($this->adminUser());
        $this->client->catchExceptions(false);

        $this->expectExceptionObject(new NotFoundHttpException('Component not found'));
        $this->client->request('GET', '/admin/gear/maintenance-config/component/gearComponent-does-not-exist/delete');
    }

    private function seedGears(): void
    {
        $gearRepository = static::getContainer()->get(GearRepository::class);
        $gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('g10130856'))
                ->withName('Race bike')
                ->build()
        );
        $gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('g1233776'))
                ->withName('Gravel bike')
                ->build()
        );
    }
}
