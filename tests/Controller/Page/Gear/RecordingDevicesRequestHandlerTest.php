<?php

namespace App\Tests\Controller\Page\Gear;

use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;

class RecordingDevicesRequestHandlerTest extends AdminWebTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/gear/recording-devices');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $this->assertMatchesHtmlSnapshot((string) $this->client->getResponse()->getContent());
    }

    public function testGetPath(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/gear/recording-devices');

        $this->assertResponseIsSuccessful();
        $this->assertStringEndsWith(
            'gear.recording-devices.auth=anon',
            (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
    }

    public function testItIsTaggedWithTheDevicesAndActivitiesItRenders(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/gear/recording-devices');

        $this->assertResponseHeaderSame(
            'X-Dreeve-Cache-Tags',
            'settings.appearance, settings.general, gear.recording-devices, activities',
        );
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }

    public function testItOnlyRendersTheAdminLinkForAuthenticatedVisitors(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/gear/recording-devices');
        $this->assertStringNotContainsString(
            'admin/gear/recording-devices',
            (string) $this->client->getResponse()->getContent(),
        );

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/gear/recording-devices');
        $this->assertStringContainsString(
            'admin/gear/recording-devices?redirectTo=%2Fgear%2Frecording-devices',
            (string) $this->client->getResponse()->getContent(),
        );
    }

    public function testItVariesByAuthentication(): void
    {
        $this->provideFullTestSet();
        $this->seedActivity();

        $this->client->request('GET', '/gear/recording-devices');
        $anonymousCacheKey = (string) $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key');
        $this->assertResponseHeaderSame('Cache-Control', 'max-age=0, must-revalidate, no-store, private');

        $this->client->loginUser($this->adminUser());
        $this->client->request('GET', '/gear/recording-devices');

        $this->assertNotEquals(
            $anonymousCacheKey,
            $this->client->getResponse()->headers->get('X-Dreeve-Cache-Key'),
        );
    }
}
