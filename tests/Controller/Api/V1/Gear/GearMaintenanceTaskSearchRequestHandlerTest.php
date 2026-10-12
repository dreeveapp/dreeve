<?php

namespace App\Tests\Controller\Api\V1\Gear;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLog;
use App\Domain\Gear\Maintenance\Log\GearMaintenanceLogRepository;
use App\Domain\Gear\Maintenance\Task\MaintenanceTaskId;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Security\Api\Token;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\ProvideGearMaintenanceConfig;
use Spatie\Snapshots\MatchesSnapshots;

class GearMaintenanceTaskSearchRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideGearMaintenanceConfig;

    private Token $token;

    public function testItListsMaintenanceTasksMostUrgentFirst(): void
    {
        $this->importGearMaintenanceConfigFromArray(Json::decode((string) file_get_contents(__DIR__.'/fixtures/gear-maintenance.json')));

        $gearRepository = static::getContainer()->get(GearRepository::class);
        $gearRepository->add(GearBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('bike-a'))->build());
        $gearRepository->add(GearBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('bike-b'))->build());
        $gearRepository->add(GearBuilder::fromDefaults()->withGearId(GearId::fromUnprefixed('retired-bike'))->withIsRetired(true)->build());

        $gearMaintenanceLogRepository = static::getContainer()->get(GearMaintenanceLogRepository::class);
        foreach (['chain-lube' => '2023-01-01', 'chain-replace' => '2023-01-01', 'pedals-service' => '2023-01-01', 'lights-charge' => '2023-10-07'] as $maintenanceTaskId => $performedOn) {
            $gearMaintenanceLogRepository->add(GearMaintenanceLog::create(
                gearId: GearId::fromUnprefixed('bike-a'),
                maintenanceTaskId: MaintenanceTaskId::fromUnprefixed($maintenanceTaskId),
                performedOn: SerializableDateTime::fromString($performedOn),
            ));
        }

        $activityRepository = static::getContainer()->get(ActivityRepository::class);
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-06-01'))
                ->withGearId(GearId::fromUnprefixed('bike-a'))
                ->withDistance(Kilometer::from(620.44))
                ->build(),
            rawData: [],
        ));
        $activityRepository->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withStartDateTime(SerializableDateTime::fromString('2023-06-02'))
                ->withGearId(GearId::fromUnprefixed('bike-b'))
                ->withDistance(Kilometer::from(370))
                ->build(),
            rawData: [],
        ));

        $this->client->request('GET', '/api/v1/gear/maintenance-tasks', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseIsSuccessful();
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testItListsNothingWhenTheFeatureIsNotEnabled(): void
    {
        $this->client->request('GET', '/api/v1/gear/maintenance-tasks', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            ['maintenanceTasks' => []],
            Json::decode((string) $this->client->getResponse()->getContent()),
        );
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }

    #[\Override]
    protected function prepareEnvironment(): void
    {
        $this->token = Token::generate();
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
    }
}
