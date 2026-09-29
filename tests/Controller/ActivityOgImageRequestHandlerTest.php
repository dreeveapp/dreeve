<?php

namespace App\Tests\Controller;

use App\Domain\Activity\OpenGraph\ActivityOpenGraphImage;

class ActivityOgImageRequestHandlerTest extends ControllerWebTestCase
{
    public function testItServesTheCardAsAPublicPng(): void
    {
        $this->client->request('GET', '/activities/activity-903645/og-image.png');

        $this->assertResponseIsSuccessful();
        $this->assertEquals('activity_og_image', $this->client->getRequest()->attributes->get('_route'));
        $this->assertResponseHeaderSame('Content-Type', 'image/png');
        $this->assertResponseHeaderSame('Cache-Control', 'max-age=3600, public');

        $size = getimagesizefromstring($this->client->getInternalResponse()->getContent());
        $this->assertIsArray($size);
        $this->assertEquals([ActivityOpenGraphImage::WIDTH, ActivityOpenGraphImage::HEIGHT], [$size[0], $size[1]]);
    }

    public function testItReturnsNotFoundForAnUnknownActivity(): void
    {
        $this->client->request('GET', '/activities/activity-999/og-image.png');

        $this->assertResponseStatusCodeSame(404);
    }
}
