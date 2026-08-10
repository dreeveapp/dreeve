<?php

namespace App\Tests\Domain\Gear\Maintenance;

use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\ProvideGearMaintenanceConfig;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class GearMaintenanceFragmentTest extends ControllerWebTestCase
{
    use MatchesSnapshots;
    use ProvideGearMaintenanceConfig;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->importGearMaintenanceConfig();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/gear/maintenance');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testRenderWhenTheFeatureIsNotEnabled(): void
    {
        $this->provideFullTestSet();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/gear/maintenance');

        $this->assertResponseIsSuccessful();
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->importGearMaintenanceConfig();
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
        $this->provideFullTestSet();
        $this->importGearMaintenanceConfig();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/data/gear/maintenance');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testItIsTaggedWithTheMaintenanceDataItRenders(): void
    {
        $this->provideFullTestSet();
        $this->importGearMaintenanceConfig();
        $this->markAppAsBuilt();

        $this->client->request('GET', '/api/fragment/page/gear/maintenance');

        $this->assertResponseHeaderSame(
            'X-Cache-Tags',
            'settings.appearance, settings.general, gear.maintenance, activities, gear',
        );
    }

    #[\Override]
    protected function shouldMarkAppAsBuilt(): bool
    {
        return false;
    }
}
