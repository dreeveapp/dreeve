<?php

namespace App\Tests\Domain\Gear\Maintenance;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\ProvideGearMaintenanceConfig;
use Spatie\Snapshots\MatchesSnapshots;

class GearMaintenanceDueFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideGearMaintenanceConfig;

    public function testRender(): void
    {
        $this->importGearMaintenanceConfig();
        // The chain needs lubing every 500km, this ride alone blows through that.
        $this->rideSinceTheChainWasLubed(Kilometer::from(750));

        $this->client->request('GET', '/api/fragment/partial/gear/maintenance-due');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        // This fragment is shared by the sidebar and the gear sub menu, so it must not carry sidebar-only classes.
        $this->assertStringNotContainsString('sidebar-collapsed', (string) $this->client->getResponse()->getContent());
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWhenNoTaskIsDue(): void
    {
        $this->importGearMaintenanceConfig();
        $this->rideSinceTheChainWasLubed(Kilometer::from(10));

        $this->client->request('GET', '/api/fragment/partial/gear/maintenance-due');

        $this->assertResponseIsSuccessful();
        $this->assertEmpty(trim((string) $this->client->getResponse()->getContent()));
    }

    public function testRenderWhenTheFeatureIsNotEnabled(): void
    {
        $this->rideSinceTheChainWasLubed(Kilometer::from(750));

        $this->client->request('GET', '/api/fragment/partial/gear/maintenance-due');

        $this->assertResponseIsSuccessful();
        $this->assertEmpty(trim((string) $this->client->getResponse()->getContent()));
    }

    public function testItIsTaggedWithTheMaintenanceDataItRenders(): void
    {
        $this->importGearMaintenanceConfig();
        $this->rideSinceTheChainWasLubed(Kilometer::from(750));

        $this->client->request('GET', '/api/fragment/partial/gear/maintenance-due');

        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, gear.maintenance, activities, gear',
        );
    }

    public function testItIsNotServedAsAPageFragment(): void
    {
        $this->importGearMaintenanceConfig();
        $this->rideSinceTheChainWasLubed(Kilometer::from(750));

        $this->client->request('GET', '/api/fragment/page/gear/maintenance-due');

        $this->assertResponseStatusCodeSame(404);
    }

    private function rideSinceTheChainWasLubed(Kilometer $distance): void
    {
        $gearId = GearId::fromUnprefixed('g1233776');

        $this->getContainer()->get(GearMaintenanceLogRepository::class)->add(GearMaintenanceLog::create(
            gearId: $gearId,
            maintenanceTaskId: MaintenanceTaskId::fromUnprefixed('chain-lubed'),
            performedOn: SerializableDateTime::fromString('2023-01-01'),
        ));

        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('ride-since-the-chain-was-lubed'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-06-01'))
                ->withGearId($gearId)
                ->withDistance($distance)
                ->build(),
            [],
        ));
    }
}
