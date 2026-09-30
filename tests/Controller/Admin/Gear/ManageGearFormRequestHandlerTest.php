<?php

namespace App\Tests\Controller\Admin\Gear;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\GearType;
use App\Domain\Import\ImportMode;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use Money\Money;

class ManageGearFormRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPageOnAdd(): void
    {
        $this->client->request('GET', '/admin/gear/add');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testAnonymousUsersAreRedirectedToTheLoginPageOnEdit(): void
    {
        $this->client->request('GET', '/admin/gear/'.GearId::fromUnprefixed('1').'/edit');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testRendersTheAddForm(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/add');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Add gear', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="add-gear"]');
        $this->assertCount(1, $form);

        $this->assertCount(0, $form->filter('input[name="gearId"]'));
        $this->assertSame('', $form->filter('input[name="name"]')->attr('value'));
        $this->assertSame('', $form->filter('input[name="purchasePriceAmount"]')->attr('value'));
        $this->assertCount(0, $form->filter('select[name="status"] option[selected]'));
    }

    public function testRendersTheEditFormPrefilledWithTheGear(): void
    {
        static::getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withName('My bike')
                ->withIsRetired(true)
                ->withPurchasePrice(Money::EUR(150000))
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/'.GearId::fromUnprefixed('1').'/edit');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Edit gear', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="update-gear"]');
        $this->assertCount(1, $form);

        $this->assertSame((string) GearId::fromUnprefixed('1'), $form->filter('input[name="gearId"]')->attr('value'));
        $this->assertSame('My bike', $form->filter('input[name="name"]')->attr('value'));
        $this->assertSame('1500.00', $form->filter('input[name="purchasePriceAmount"]')->attr('value'));
        $this->assertSame('EUR', $form->filter('select[name="purchasePriceCurrency"] option[selected]')->attr('value'));
        $this->assertSame('retired', $form->filter('select[name="status"] option[selected]')->attr('value'));
    }

    public function testImportedGearInStravaApiModeDisablesNameAndStatus(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);

        static::getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withGearType(GearType::IMPORTED)
                ->withName('Strava bike')
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/'.GearId::fromUnprefixed('1').'/edit');

        $this->assertResponseIsSuccessful();

        // Name and status are disabled because they are imported from Strava.
        $this->assertNotNull($crawler->filter('input#gear-name')->attr('disabled'));
        $this->assertNotNull($crawler->filter('select#gear-status')->attr('disabled'));

        // Disabled fields are not submitted, so their values are mirrored into hidden inputs.
        $this->assertSame('Strava bike', $crawler->filter('input[type="hidden"][name="name"]')->attr('value'));
        $this->assertCount(1, $crawler->filter('input[type="hidden"][name="status"]'));

        // The purchase price stays editable.
        $this->assertNull($crawler->filter('input[name="purchasePriceAmount"]')->attr('disabled'));
    }

    public function testCustomGearStaysEditableInStravaApiMode(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);

        static::getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withGearType(GearType::CUSTOM)
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/'.GearId::fromUnprefixed('1').'/edit');

        $this->assertResponseIsSuccessful();
        $this->assertNull($crawler->filter('input#gear-name')->attr('disabled'));
        $this->assertNull($crawler->filter('select#gear-status')->attr('disabled'));
        $this->assertCount(0, $crawler->filter('input[type="hidden"][name="status"]'));
    }

    public function testAnonymousUsersAreRedirectedToTheLoginPageOnDelete(): void
    {
        $this->client->request('GET', '/admin/gear/'.GearId::fromUnprefixed('1').'/delete');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testTheAddFormHasNoDeleteButton(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/add');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('a.btn--danger'));
    }

    public function testCustomGearHasADeleteButton(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);

        static::getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withGearType(GearType::CUSTOM)
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/'.GearId::fromUnprefixed('1').'/edit');

        $this->assertResponseIsSuccessful();
        $this->assertSame('/admin/gear/gear-1/delete', $crawler->filter('a.btn--danger')->attr('href'));
    }

    public function testImportedGearInStravaApiModeCannotBeDeleted(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);

        static::getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withGearType(GearType::IMPORTED)
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/'.GearId::fromUnprefixed('1').'/edit');
        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('a.btn--danger'));

        $this->client->request('GET', '/admin/gear/'.GearId::fromUnprefixed('1').'/delete');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testImportedGearInFilesModeHasADeleteButton(): void
    {
        $this->withImportMode(ImportMode::FILES);

        static::getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withGearType(GearType::IMPORTED)
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/'.GearId::fromUnprefixed('1').'/edit');

        $this->assertResponseIsSuccessful();
        $this->assertSame('/admin/gear/gear-1/delete', $crawler->filter('a.btn--danger')->attr('href'));
    }

    public function testDeleteConfirmationForUnusedGear(): void
    {
        static::getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withGearType(GearType::CUSTOM)
                ->withName('My bike')
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/'.GearId::fromUnprefixed('1').'/delete');

        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[data-dispatch-command="delete-gear"]');
        $this->assertSame('gear-1', $form->filter('input[name="gearId"]')->attr('value'));
        $this->assertCount(1, $form->filter('button[type="submit"].btn--danger'));
        $this->assertStringContainsString('Are you sure you want to delete My bike?', $crawler->filter('.modal-like-form')->text());
    }

    public function testDeleteConfirmationForGearInUseSuggestsRetiring(): void
    {
        static::getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('1'))
                ->withGearType(GearType::CUSTOM)
                ->withName('My bike')
                ->build()
        );
        static::getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId(GearId::fromUnprefixed('1'))
                ->build(),
            []
        ));

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/gear/'.GearId::fromUnprefixed('1').'/delete');

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('form[data-dispatch-command="delete-gear"]'));
        $this->assertCount(0, $crawler->filter('button[type="submit"]'));
        $this->assertStringContainsString("My bike can't be deleted", $crawler->filter('.modal-like-form')->text());
        $this->assertStringContainsString('Set its status to Retired', $crawler->filter('.modal-like-form')->text());
        $this->assertCount(0, $crawler->filter('.modal-like-form a.btn--primary, .modal-like-form a.btn--secondary'));
    }
}
