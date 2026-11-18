<?php

namespace App\Tests\Controller\Admin\Gear\Maintenance;

use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\MaintenanceTask;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Infrastructure\ValueObject\String\Name;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Domain\Gear\Maintenance\GearComponentBuilder;
use App\Tests\Domain\Media\MediaItemBuilder;
use PHPUnit\Framework\Attributes\TestWith;

class ManageGearMaintenanceComponentFormRequestHandlerTest extends AdminWebTestCase
{
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
        $this->assertStringContainsString('value="gear:gear-g10130856"', $installationTemplate);
        $this->assertStringContainsString('name="installations[__index__][sportTypes][]"', $installationTemplate);
        $this->assertStringContainsString('data-repeater-field="sportTypes"', $installationTemplate);
        $this->assertStringContainsString('value="VirtualRide"', $installationTemplate);

        $this->assertCount(1, $form->filter('input[name="purchasePriceAmount"]'));
        $this->assertCount(1, $form->filter('select[name="purchasePriceCurrency"]'));

        $tasksRepeater = $form->filter('[data-repeater]')->eq(1);
        $this->assertCount(2, $form->filter('[data-repeater]'));
        $this->assertSame('0', $tasksRepeater->attr('data-repeater-min'));
        $this->assertSame('[]', $tasksRepeater->filter('[data-repeater-list]')->attr('data-repeater-initial'));
    }

    public function testRendersTheEditFormPrefilledWithTheComponent(): void
    {
        static::getContainer()->get(MediaItemRepository::class)->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('chain'))
            ->withPath(MediaItemPath::fromString('gear-maintenance/chain.png'))
            ->build());
        $gearComponentRepository = static::getContainer()->get(GearComponentRepository::class);
        $gearComponentRepository->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('chain'))
            ->withLabel('Some cool chain')
            ->withMediaItemId(MediaItemId::fromUnprefixed('chain'))
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('g1233776')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('g10130856')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withInstallation(GearComponentInstallation::create(
                parent: InstallationParent::gear(GearId::fromUnprefixed('retired')),
                from: null,
                until: null,
                sportTypes: SportTypes::empty(),
            ))
            ->withMaintenanceTask(MaintenanceTask::create(
                id: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
                label: Name::fromString('Lube'),
                intervalValue: 500,
                intervalUnit: IntervalUnit::EVERY_X_KILOMETERS_USED,
            ))
            ->build());
        $gearComponentRepository->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('di-2'))
            ->withLabel('DI2 Battery')
            ->build());
        $this->seedGears();

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/maintenance-config/component/gearComponent-chain/edit');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Edit component', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="update-gear-maintenance-component"]');
        $this->assertCount(1, $form);

        $this->assertSame('gearComponent-chain', $form->filter('input[name="gearComponentId"]')->attr('value'));
        $this->assertSame('Some cool chain', $form->filter('input[name="label"]')->attr('value'));
        $this->assertCount(1, $form->filter('input[name="isRetired"][value="true"]'));
        $this->assertCount(0, $form->filter('input[name="isRetired"][checked]'));
        $this->assertSame('', $form->filter('textarea[name="notes"]')->text());

        $this->assertSame(
            '[{"parent":"gear:gear-g1233776","from":null,"until":null,"sportTypes":[]},{"parent":"gear:gear-g10130856","from":null,"until":null,"sportTypes":[]},{"parent":"gear:gear-retired","from":null,"until":null,"sportTypes":[]}]',
            $form->filter('[data-repeater-list]')->eq(0)->attr('data-repeater-initial')
        );

        $installationTemplate = $form->filter('template[data-repeater-template]')->eq(0)->html();
        $this->assertStringContainsString('value="component:gearComponent-di-2"', $installationTemplate);
        $this->assertStringNotContainsString('value="component:gearComponent-chain"', $installationTemplate);

        $initialTasks = (string) $form->filter('[data-repeater-list]')->eq(1)->attr('data-repeater-initial');
        $this->assertStringContainsString('Lube', $initialTasks);
        $this->assertStringContainsString('chain-lubed', $initialTasks);

        $this->assertSame(
            [['url' => '/files/gear-maintenance/chain.png', 'name' => 'chain.png', 'id' => 'mediaItem-chain']],
            json_decode((string) $form->filter('[data-image-dropzone]')->attr('data-existing-images'), true),
        );
    }

    public function testItEscapesNotesInTheEditForm(): void
    {
        static::getContainer()->get(GearComponentRepository::class)->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('chain'))
            ->withNotes('</textarea><b>Serial</b>')
            ->build());
        $this->seedGears();

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/maintenance-config/component/gearComponent-chain/edit');

        $this->assertResponseIsSuccessful();
        $this->assertSame('</textarea><b>Serial</b>', $crawler->filter('textarea[name="notes"]')->text());
    }

    public function testRendersTheDeleteConfirmation(): void
    {
        static::getContainer()->get(GearComponentRepository::class)->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('chain'))
            ->withLabel('Some cool chain')
            ->build());

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
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/gear/maintenance-config/component/gearComponent-does-not-exist/'.$action);
        $this->assertResponseStatusCodeSame(404);
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
