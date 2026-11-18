<?php

namespace App\Tests\Controller\Admin\Gear\Maintenance;

use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Domain\Gear\Maintenance\GearComponentBuilder;
use App\Tests\Domain\Media\MediaItemBuilder;
use Symfony\Component\DomCrawler\Crawler;

class ManageGearMaintenanceConfigRequestHandlerTest extends AdminWebTestCase
{
    public function testItRendersTheConfigReflectingTheCurrentSettings(): void
    {
        $mediaItemRepository = $this->getContainer()->get(MediaItemRepository::class);
        $mediaItemRepository->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('chain'))
            ->withPath(MediaItemPath::fromString('gear-maintenance/chain.png'))
            ->build());
        $mediaItemRepository->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('battery'))
            ->withPath(MediaItemPath::fromString('gear-maintenance/battery.png'))
            ->build());
        $this->getContainer()->get(GearMaintenanceRepository::class)->updateConfig(
            isFeatureEnabled: true,
            ignoreRetiredGear: true,
        );
        $gearComponentRepository = $this->getContainer()->get(GearComponentRepository::class);
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
            ->build());
        $gearComponentRepository->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('di-2'))
            ->withLabel('DI2 Battery')
            ->withMediaItemId(MediaItemId::fromUnprefixed('battery'))
            ->build());
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
        $this->assertSame(
            ['/files/gear-maintenance/battery.png', '/files/gear-maintenance/chain.png'],
            $crawler->filter('table.data-table tbody img')->each(static fn (Crawler $image): ?string => $image->attr('src')),
        );

        $this->assertCount(1, $crawler->filter('.alert.alert--warning'));
        $warningText = $crawler->filter('.alert.alert--warning')->text();
        $this->assertStringContainsString('Gear "g1233776" is attached to one of your components, but does not exist', $warningText);
        $this->assertStringNotContainsString('10130856', $warningText);
    }

    public function testItMarksComponentsThatAreNotFitted(): void
    {
        $this->getContainer()->get(GearMaintenanceRepository::class)->updateConfig(
            isFeatureEnabled: true,
            ignoreRetiredGear: false,
        );
        $gearComponentRepository = $this->getContainer()->get(GearComponentRepository::class);
        foreach (Json::decode((string) file_get_contents(__DIR__.'/fixtures/gear-maintenance-with-not-fitted-component.json')) as $component) {
            $gearComponentRepository->add(GearComponent::fromArray($component));
        }
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
        $this->assertSame(
            ['Old chain', 'Summer wheelset', 'Winter wheelset'],
            $rows->each(static fn (Crawler $row): string => $row->filter('td')->first()->text()),
        );
        $this->assertCount(1, $rows->eq(0)->filter('.pill--danger'));
        $this->assertCount(0, $rows->eq(0)->filter('.pill--warning'));
        $this->assertCount(1, $rows->eq(1)->filter('.pill--warning'));
        $this->assertStringNotContainsString('Road bike', $rows->eq(1)->text());
        $this->assertCount(0, $rows->eq(2)->filter('.pill--warning'));
        $this->assertStringContainsString('Road bike', $rows->eq(2)->text());
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
