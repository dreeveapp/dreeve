<?php

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\ValueObject\String\Name;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\ProvideGearMaintenanceConfig;
use Spatie\Snapshots\MatchesSnapshots;

class GearMaintenanceFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideGearMaintenanceConfig;

    public function testRender(): void
    {
        $this->importGearMaintenanceConfig();
        $this->provideGearWithMaintenanceHistory();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/gear/maintenance');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWhenTheFeatureIsNotEnabled(): void
    {
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/gear/maintenance');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->importGearMaintenanceConfig();
        $this->provideGearWithMaintenanceHistory();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/gear/maintenance');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'gear.maintenance',
            (string) $this->client->getResponse()->headers->get('X-Cache-Key'),
        );
    }

    public function testItIsNotServedAsADataFragment(): void
    {
        $this->importGearMaintenanceConfig();
        $this->provideGearWithMaintenanceHistory();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/data/gear/maintenance');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItIsTaggedWithTheMaintenanceDataItRenders(): void
    {
        $this->importGearMaintenanceConfig();
        $this->provideGearWithMaintenanceHistory();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/gear/maintenance');

        $this->assertResponseHeaderSame(
            'X-Cache-Tags',
            'settings.appearance, settings.general, gear.maintenance, activities, gear',
        );
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
    protected function shouldMarkAppAsBuilt(): bool
    {
        return false;
    }
}
