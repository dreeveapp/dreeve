<?php

namespace App\Tests\Controller\Admin\Segment;

use App\Domain\Import\ImportMode;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\Admin\AdminWebTestCase;

class ManageSegmentFormRequestHandlerTest extends AdminWebTestCase
{
    public function testRendersTheAddForm(): void
    {
        $this->withImportMode(ImportMode::FILES);
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/segments/add');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('head link[rel="stylesheet"][href*="libraries/leaflet/leaflet.min.css"]'));
        $form = $crawler->filter('form[data-dispatch-command="add-custom-segment"]');
        $this->assertCount(1, $form);
        $this->assertStringEndsWith('/admin/segments', (string) $form->attr('data-redirect'));
        $this->assertStringEndsWith('/admin/activities/search', (string) $form->filter('#segment-activity')->attr('data-autocomplete-url'));
        $this->assertCount(1, $form->filter('input[type="hidden"][name="startIndex"]'));
        $this->assertCount(1, $form->filter('input[type="hidden"][name="endIndex"]'));
        $this->assertCount(1, $form->filter('input[name="name"][required]'));
        $this->assertCount(1, $form->filter('input[type="checkbox"][name="isFavourite"][value="true"]'));

        $picker = $form->filter('[data-route-range-picker]');
        $this->assertSame('empty', $picker->attr('data-state'));
        $options = Json::decode((string) $picker->attr('data-route-range-picker'));
        $this->assertStringEndsWith('/admin/activities/activity-__ID__/route', $options['url']);
        $this->assertSame('activity-__ID__', $options['placeholder']);
        $this->assertSame('#segment-activity', $options['source']);
        $this->assertEquals(1000, $options['distanceInMetersPerUnit']);
        $this->assertEquals(1, $options['elevationInMetersPerUnit']);
    }

    public function testItIsNotAvailableInStravaApiMode(): void
    {
        $this->withImportMode(ImportMode::STRAVA_API);
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/segments/add');

        $this->assertResponseStatusCodeSame(404);
    }
}
