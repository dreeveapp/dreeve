<?php

namespace App\Tests\Controller\Admin\Gear\Maintenance;

use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\ProvideGearMaintenanceConfig;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ManageGearMaintenanceComponentFormRequestHandlerTest extends AdminWebTestCase
{
    use ProvideGearMaintenanceConfig;

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

        $installationsRepeater = $form->filter('[data-repeater]')->eq(0);
        $this->assertSame('0', $installationsRepeater->attr('data-repeater-min'));
        $this->assertSame('[]', $installationsRepeater->filter('[data-repeater-list]')->attr('data-repeater-initial'));
        $this->assertCount(3, $installationsRepeater->filter('template[data-repeater-template] select[name="installations[__index__][parent]"] option'));
        $installationTemplate = $installationsRepeater->filter('template[data-repeater-template]')->html();
        $this->assertStringContainsString('Race bike', $installationTemplate);
        $this->assertStringContainsString('data-repeater-field="parent"', $installationTemplate);
        $this->assertStringContainsString('value="gear:g10130856"', $installationTemplate);
        $this->assertStringContainsString('name="installations[__index__][sportTypes][]"', $installationTemplate);
        $this->assertStringContainsString('data-repeater-field="sportTypes"', $installationTemplate);
        $this->assertStringContainsString('value="VirtualRide"', $installationTemplate);

        $this->assertCount(1, $form->filter('input[name="purchasePriceAmount"]'));
        $this->assertCount(1, $form->filter('select[name="purchasePriceCurrency"]'));

        $tasksRepeater = $form->filter('[data-repeater]')->eq(1);
        $this->assertCount(2, $form->filter('[data-repeater]'));
        $this->assertSame('1', $tasksRepeater->attr('data-repeater-min'));
        $this->assertSame('[]', $tasksRepeater->filter('[data-repeater-list]')->attr('data-repeater-initial'));
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

        $this->assertSame(
            '[{"parent":"gear:g1233776","from":null,"until":null,"sportTypes":[]},{"parent":"gear:g10130856","from":null,"until":null,"sportTypes":[]},{"parent":"gear:retired","from":null,"until":null,"sportTypes":[]}]',
            $form->filter('[data-repeater-list]')->eq(0)->attr('data-repeater-initial')
        );

        $installationTemplate = $form->filter('template[data-repeater-template]')->eq(0)->html();
        $this->assertStringContainsString('value="component:gearComponent-di-2"', $installationTemplate);
        $this->assertStringNotContainsString('value="component:gearComponent-chain"', $installationTemplate);

        $initialTasks = $form->filter('[data-repeater-list]')->eq(1)->attr('data-repeater-initial');
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

    #[TestWith(['edit'])]
    #[TestWith(['delete'])]
    public function testReturns404ForAnUnknownComponent(string $action): void
    {
        $this->importGearMaintenanceConfig();

        $this->client->loginUser($this->adminUser());
        $this->client->catchExceptions(false);

        $this->expectExceptionObject(new NotFoundHttpException('Component not found'));
        $this->client->request('GET', '/admin/gear/maintenance-config/component/gearComponent-does-not-exist/'.$action);
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
