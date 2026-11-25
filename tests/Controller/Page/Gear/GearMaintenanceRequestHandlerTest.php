<?php

namespace App\Tests\Controller\Page\Gear;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\SportType\SportTypes;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\GearComponent;
use App\Domain\Gear\Maintenance\GearComponentId;
use App\Domain\Gear\Maintenance\GearComponentRepository;
use App\Domain\Gear\Maintenance\GearMaintenanceRepository;
use App\Domain\Gear\Maintenance\Installation\GearComponentInstallation;
use App\Domain\Gear\Maintenance\Installation\InstallationParent;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\IntervalUnit;
use App\Domain\Gear\Maintenance\Task\MaintenanceTask;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Domain\Gear\Maintenance\GearComponentBuilder;
use App\Tests\Domain\Media\MediaItemBuilder;
use App\Tests\MatchesVersionlessSnapshots;
use Money\Money;

class GearMaintenanceRequestHandlerTest extends AdminWebTestCase
{
    use MatchesVersionlessSnapshots;

    public function testRender(): void
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
            ->withPurchasePrice(Money::EUR(123456))
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
            ->withMaintenanceTask(MaintenanceTask::create(
                id: MaintenanceTaskId::fromUnprefixed('chain-replaced'),
                label: Name::fromString('Replace'),
                intervalValue: 1000,
                intervalUnit: IntervalUnit::EVERY_X_KILOMETERS_USED,
            ))
            ->withMaintenanceTask(MaintenanceTask::create(
                id: MaintenanceTaskId::fromUnprefixed('chain-cleaned'),
                label: Name::fromString('Clean'),
                intervalValue: 1000,
                intervalUnit: IntervalUnit::EVERY_X_KILOMETERS_USED,
            ))
            ->build());
        $gearComponentRepository->add(GearComponentBuilder::fromDefaults()
            ->withGearComponentId(GearComponentId::fromUnprefixed('di-2'))
            ->withLabel('DI2 Battery')
            ->withMediaItemId(MediaItemId::fromUnprefixed('battery'))
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
            ->withMaintenanceTask(MaintenanceTask::create(
                id: MaintenanceTaskId::fromUnprefixed('di-2-charged'),
                label: Name::fromString('Charge'),
                intervalValue: 11,
                intervalUnit: IntervalUnit::EVERY_X_HOURS_USED,
            ))
            ->build());
        $this->provideGearWithMaintenanceHistory();
        $this->seedActivity();

        $this->client->request('GET', '/gear/maintenance');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringEndsWith(
            'gear.maintenance.auth=anon',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, gear.maintenance, activities, gear',
        );
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWithAComponentThatIsNotFitted(): void
    {
        $this->getContainer()->get(GearMaintenanceRepository::class)->updateConfig(
            isFeatureEnabled: true,
            ignoreRetiredGear: false,
        );
        $gearComponentRepository = $this->getContainer()->get(GearComponentRepository::class);
        foreach (Json::decode((string) file_get_contents(__DIR__.'/fixtures/gear-maintenance-with-not-fitted-component.json')) as $component) {
            $gearComponentRepository->add(GearComponent::fromArray($component));
        }
        $this->getContainer()->get(MediaItemRepository::class)->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('bike'))
            ->withPath(MediaItemPath::fromString('gear/bike.jpg'))
            ->build());
        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('g10130856'))
                ->withMediaItemId(MediaItemId::fromUnprefixed('bike'))
                ->build()
        );
        $this->seedActivity();

        $this->client->request('GET', '/gear/maintenance');

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('Old chain', (string) $this->client->getResponse()->getContent());
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWhenTheFeatureIsNotEnabled(): void
    {
        $this->seedActivity();

        $crawler = $this->client->request('GET', '/gear/maintenance');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('This feature is currently disabled.', $crawler->filter('.alert--info')->text());
    }

    private function provideGearWithMaintenanceHistory(): void
    {
        $gearRepository = $this->getContainer()->get(GearRepository::class);
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $gearMaintenanceLogRepository = $this->getContainer()->get(GearMaintenanceLogRepository::class);

        $gear = GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed('g10130856'))
            ->build();
        $gearRepository->add($gear);
        $gearRepository->add(GearBuilder::fromDefaults()
            ->withGearId(GearId::fromUnprefixed('retired'))
            ->withIsRetired(true)
            ->build());

        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withName(Name::fromString('#sfs-chain-lubed'))
                ->withGearId($gear->getId())
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 00:00:00'))
                ->build(),
            []
        ));
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('10'))
                ->withName(Name::fromString('#sfs-chain-lubed'))
                ->withGearId(GearId::fromUnprefixed('retired'))
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 00:00:00'))
                ->build(),
            []
        ));
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId($gear->getId())
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 01:00:00'))
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withGearId($gear->getId())
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 02:00:00'))
                ->withMovingTimeInSeconds(3600)
                ->build(),
            []
        ));
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('4'))
                ->withName(Name::fromString('#sfs-chain-lubed wrong'))
                ->withGearId(GearId::random())
                ->withStartDateTime(SerializableDateTime::fromString('2025-01-01 00:00:00'))
                ->build(),
            []
        ));

        $gearMaintenanceLogRepository->add(GearMaintenanceLog::create(
            gearId: $gear->getId(),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        ));
        $gearMaintenanceLogRepository->add(GearMaintenanceLog::create(
            gearId: GearId::fromUnprefixed('retired'),
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2025-01-01 00:00:00'),
        ));
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }

    public function testItDoesNotOfferToLogMaintenanceWhenTheFeatureIsNotEnabled(): void
    {
        $this->seedActivity();

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/gear/maintenance');

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('admin/gear/maintenance-logs/register', $content);
        $this->assertStringContainsString('admin/gear/maintenance-config?redirectTo=%2Fgear%2Fmaintenance', $content);
    }
}
